<?php

namespace App\Services\Voters;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\Voter;
use App\Services\Auth\AuditService;
use Illuminate\Support\Facades\DB;

class VoterService
{
    /** Excel streams rows, so a whole assembly fits. */
    public const MAX_EXPORT_ROWS = 500000;
    /** PDF lays out every page in memory: about 4.5 MB per 1,000 rows (PDF_MAX_ROWS in .env). */
    public static function maxPdfRows(): int { return max(100, (int) config('voters.export.pdf_max_rows', 5000)); }

    public function __construct(private AuditService $audit) {}

    /** A text search counts at most this many matches ("1,000+"): counting every match reads the whole assembly. */
    public const SEARCH_COUNT_CAP = 1000;

    public function search(VoterFilter $f)
    {
        if (!$f->get('q')) return $f->applySort($f->apply(Voter::query()))->paginate($f->size, ['*'], 'page', $f->page + 1);
        return self::timeLimited(function () use ($f) {
            $base = $f->apply(Voter::query());
            $total = DB::query()->fromSub((clone $base)->select('voters.id')->limit(self::SEARCH_COUNT_CAP + 1)->toBase(), 'm')->count();
            $items = $f->applySort(clone $base)->forPage($f->page + 1, $f->size)->get();
            $p = new \Illuminate\Pagination\LengthAwarePaginator($items, min($total, self::SEARCH_COUNT_CAP), $f->size, $f->page + 1,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'pageName' => 'page']);
            $p->capped = $total > self::SEARCH_COUNT_CAP;
            return $p;
        });
    }

    /**
     * Runs a heavy search with a time limit (SEARCH_SECONDS, default 8) so that a few broad searches cannot hold every
     * server process on shared hosting. Throws SearchTooSlow when the database stops the query.
     */
    public static function timeLimited(\Closure $fn)
    {
        $secs = (int) config('voters.search_seconds', 8);
        $db = DB::connection();
        if ($secs <= 0 || $db->getDriverName() !== 'mysql') return $fn();
        $maria = stripos((string) $db->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), 'mariadb') !== false;
        $set = fn ($on) => $maria ? $db->statement('SET SESSION max_statement_time = ' . ($on ? $secs : 0))
                                   : $db->statement('SET SESSION max_execution_time = ' . ($on ? $secs * 1000 : 0));
        try { $set(true); } catch (\Throwable $e) { return $fn(); }   // not allowed on this server: run without a limit
        try {
            return $fn();
        } catch (\Illuminate\Database\QueryException $e) {
            if (in_array((int) ($e->errorInfo[1] ?? 0), [1969, 3024, 1317], true)) throw new SearchTooSlow();
            throw $e;
        } finally {
            try { $set(false); } catch (\Throwable $e) {}
        }
    }

    public function searchAll(VoterFilter $f, int $max = self::MAX_EXPORT_ROWS)
    {
        return $f->applySort($f->apply(Voter::query()))->limit($max)->get();
    }

    /** Same rows read 2,000 at a time, for streaming exports. */
    public function searchLazy(VoterFilter $f, int $max = self::MAX_EXPORT_ROWS)
    {
        return $f->applySort($f->apply(Voter::query()))->limit($max)->lazy(2000);
    }

    public function count(VoterFilter $f): int
    {
        return $f->apply(Voter::query())->count();
    }

    /** Everyone at the same assembly, house no., address and village/ward (ignoring case and spaces). */
    private static ?bool $houseKey = null;
    public static function hasHouseKey(): bool { return self::$houseKey ??= \Illuminate\Support\Facades\Schema::hasColumn('voters', 'house_key'); }

    public function household(Voter $v)
    {
        $q = Voter::where('assembly_no', $v->assembly_no);
        foreach (['house_no', 'address', 'village_ward'] as $c) {
            $val = $v->$c;
            if ($val === null || trim($val) === '') $q->where(fn ($w) => $w->whereNull($c)->orWhere($c, ''));
            elseif ($c === 'house_no') self::hasHouseKey()   // RZ-154 = R Z-154; the indexed house_key column when the upgrade has run
                ? $q->where('house_key', \App\Support\Text::houseKey($val))
                : $q->whereRaw(\App\Support\Text::HOUSE_KEY_SQL . ' = ?', [\App\Support\Text::houseKey($val)]);
            else $q->whereRaw("lower(trim($c)) = ?", [mb_strtolower(trim($val))]);
        }
        return $q->orderBy('serial_no')->orderBy('age')->orderBy('id')->get();
    }

    /** Saves; assembly and booth names come from the masters when left empty. */
    public function save(Voter $v): Voter
    {
        $isNew = !$v->exists;
        if (!$v->assembly_name) $v->assembly_name = Assembly::where('assembly_no', $v->assembly_no)->value('name');
        if (!$v->booth_name) $v->booth_name = Booth::where('assembly_no', $v->assembly_no)->where('booth_no', $v->booth_no)->value('name');
        $v->save();
        $this->audit->log($isNew ? 'CREATE' : 'UPDATE', 'VOTER', $v->id, $v->epic_no . ' ' . $v->name);
        return $v;
    }

    public function delete(Voter $v): void
    {
        $v->delete();
        $this->audit->log('DELETE', 'VOTER', $v->id, $v->epic_no . ' ' . $v->name);
    }

    /** Assembly no => name, from voters and the assembly master. */
    public function assemblies(): array
    {
        $m = [];
        foreach ($this->cached('assemblies', [], fn () => Voter::select('assembly_no', DB::raw('max(assembly_name) as n'))->groupBy('assembly_no')->get()->map(fn ($r) => [$r->assembly_no, (string) $r->n])->all()) as [$no, $n]) $m[$no] = $n;
        foreach (Assembly::orderBy('assembly_no')->get() as $a) $m[$a->assembly_no] = $a->name;
        ksort($m);
        return $m;
    }

    /**
     * Lists for drop-downs and header counts read the whole assembly (lakhs of rows), so they are kept for a few minutes
     * (LOOKUP_CACHE_SECONDS, default 300) per assembly and per access area. New values appear within that time.
     */
    private function cached(string $name, array $args, \Closure $fn)
    {
        $ttl = (int) config('voters.lookup_cache_seconds', 300);
        if ($ttl <= 0) return $fn();
        $who = json_encode([\App\Support\Area::current(), app(\App\Services\Auth\CurrentUser::class)->workerId()]);
        return \Illuminate\Support\Facades\Cache::remember('vis.lookup.' . $name . '.' . md5(json_encode($args) . $who), $ttl, $fn);
    }

    public function booths(?int $a, ?int $ward = null): array
    {
        return $this->cached('booths', [$a, $ward], fn () => Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->when($ward, fn ($q) => $q->where('ward_no', $ward))
            ->distinct()->orderBy('booth_no')->pluck('booth_no')->all());
    }

    /** Ward numbers present in the voter data (of one assembly, or all). */
    public function wards(?int $a): array
    {
        if (!Voter::showWard()) return [];   // no Ward No drop-downs anywhere
        return $this->cached('wards', [$a], fn () => Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull('ward_no')->distinct()->orderBy('ward_no')->pluck('ward_no')->all());
    }

    /** Which booths lie in which ward: [ward_no => [booth_no, ...]] - drives the Booth drop-down when a ward is chosen. */
    public function boothsByWard(?int $a): array
    {
        if (!Voter::showWard()) return [];
        return $this->cached('boothsByWard', [$a], function () use ($a) {
        $map = [];
        foreach (Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull('ward_no')->select('ward_no', 'booth_no')->distinct()
                     ->orderBy('ward_no')->orderBy('booth_no')->get() as $r) $map[$r->ward_no][] = $r->booth_no;
        return $map;
        });
    }
    public function districts(?int $a): array { return $this->cached('districts', [$a], fn () => Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull('district')->where('district', '<>', '')->distinct()->orderBy('district')->pluck('district')->all()); }
    public function villages(?int $a): array { return $this->cached('villages', [$a], fn () => Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull('village_ward')->where('village_ward', '<>', '')->distinct()->orderBy('village_ward')->pluck('village_ward')->all()); }

    /** Distinct non-empty values of a column (filter drop-downs: caste, political status). */
    public function distinctValues(string $column, ?int $a): array
    {
        return $this->cached('distinct.' . $column, [$a], fn () => Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull($column)->where($column, '<>', '')
            ->distinct()->orderBy($column)->limit(300)->pluck($column)->all());
    }

    public function total(?int $a): int { return $this->cached('total', [$a], fn () => Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->count()); }

    public function genderCounts(?int $a): array
    {
        return $this->cached('genders', [$a], function () use ($a) {
            $m = ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0];
            foreach (Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->select('gender', DB::raw('count(*) as c'))->groupBy('gender')->get() as $r) $m[$r->gender] = (int) $r->c;
            return $m;
        });
    }

    /** Validation rules (messages are translated keys). */
    public static function rules(?int $id = null): array
    {
        return [
            'epic_no' => ['required', 'max:20'],
            'name' => ['required', 'max:120'],
            'name_local' => ['nullable', 'max:120'],
            'relative_name' => ['nullable', 'max:120'],
            'relative_name_local' => ['nullable', 'max:120'],
            'relation_type' => ['nullable', 'in:FATHER,HUSBAND,MOTHER,OTHER'],
            'gender' => ['required', 'in:MALE,FEMALE,OTHER'],
            'age' => ['nullable', 'integer', 'between:18,130'],
            'assembly_no' => ['required', 'integer', 'min:1'],
            'assembly_name' => ['nullable', 'max:100'],
            'booth_no' => ['required', 'integer', 'min:1'],
            'booth_name' => ['nullable', 'max:200'],
            'serial_no' => ['nullable', 'integer', 'min:1'],
            'house_no' => ['nullable', 'max:30'],
            'address' => ['nullable', 'max:300'],
            'address_local' => ['nullable', 'max:300'],
            'village_ward' => ['nullable', 'max:100'],
            'ward_no' => ['nullable', 'integer', 'min:1'],
            'district' => ['nullable', 'max:100'],
            'pincode' => ['nullable', 'regex:/^[0-9]{6}$/'],
            'mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'alt_mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'category' => ['nullable', 'max:50'],
            'remarks' => ['nullable', 'max:500'],
        ] + array_map(fn ($d) => match ($d[1]) {
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'checkbox' => ['nullable', 'boolean'],
            default => ['nullable', 'max:' . $d[2]],
        }, Voter::PROFILE);
    }
}
