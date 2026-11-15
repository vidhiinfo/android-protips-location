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

    public function search(VoterFilter $f)
    {
        return $f->applySort($f->apply(Voter::query()))->paginate($f->size, ['*'], 'page', $f->page + 1);
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
    public function household(Voter $v)
    {
        $q = Voter::where('assembly_no', $v->assembly_no);
        foreach (['house_no', 'address', 'village_ward'] as $c) {
            $val = $v->$c;
            if ($val === null || trim($val) === '') $q->where(fn ($w) => $w->whereNull($c)->orWhere($c, ''));
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
        foreach (Voter::select('assembly_no', DB::raw('max(assembly_name) as n'))->groupBy('assembly_no')->get() as $r) $m[$r->assembly_no] = (string) $r->n;
        foreach (Assembly::orderBy('assembly_no')->get() as $a) $m[$a->assembly_no] = $a->name;
        ksort($m);
        return $m;
    }

    public function booths(?int $a): array { return Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->distinct()->orderBy('booth_no')->pluck('booth_no')->all(); }
    public function districts(?int $a): array { return Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull('district')->where('district', '<>', '')->distinct()->orderBy('district')->pluck('district')->all(); }
    public function villages(?int $a): array { return Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull('village_ward')->where('village_ward', '<>', '')->distinct()->orderBy('village_ward')->pluck('village_ward')->all(); }

    /** Distinct non-empty values of a column (filter drop-downs: caste, political status). */
    public function distinctValues(string $column, ?int $a): array
    {
        return Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->whereNotNull($column)->where($column, '<>', '')
            ->distinct()->orderBy($column)->limit(300)->pluck($column)->all();
    }

    public function total(?int $a): int { return Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->count(); }

    public function genderCounts(?int $a): array
    {
        $m = ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0];
        foreach (Voter::when($a, fn ($q) => $q->where('assembly_no', $a))->select('gender', DB::raw('count(*) as c'))->groupBy('gender')->get() as $r) $m[$r->gender] = (int) $r->c;
        return $m;
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
