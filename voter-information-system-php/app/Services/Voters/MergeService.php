<?php

namespace App\Services\Voters;

use App\Models\Voter;
use App\Services\Auth\AuditService;
use App\Services\Privacy\PrivacyService;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate voters: finds the same person entered twice (same name + father / husband name in an assembly) and merges them.
 * The kept voter receives the information the duplicate has and it lacks (mobile, caste, status ...), grievances / poll-day marks /
 * consent history move to it, and the duplicate is marked deleted. Every merge is recorded and can be undone.
 */
class MergeService
{
    /** Rows of other tables that point at a voter and move to the kept voter. */
    private const LINKED = ['grievances', 'poll_marks', 'consent_log', 'privacy_requests'];

    public function __construct(private AuditService $audit) {}

    public static function enabled(): bool { return Schema::hasTable('voter_merges'); }

    /** Fields copied from the duplicate when the kept voter has them empty. */
    public static function fields(): array
    {
        $f = PrivacyService::partyFields();
        if (PrivacyService::enabled()) $f = array_merge($f, ['consent_contact', 'consent_at', 'consent_by', 'consent_source']);
        return array_values(array_filter($f, fn ($c) => Schema::hasColumn('voters', $c)));
    }

    /**
     * Groups of likely duplicates in an assembly (voters not deleted): [[voter, voter, ...], ...] in roll order.
     * $sameAge: only voters whose ages differ by at most 3 years (or are unknown).
     */
    public function groups(int $assembly, ?int $booth, bool $sameAge, int $offset, int $limit, ?int &$total = null): array
    {
        $keys = [];
        $q = Voter::where('assembly_no', $assembly)->where('deleted', false)->select('id', 'name', 'relative_name', 'age')
            ->orderBy('booth_no')->orderBy('serial_no')->orderBy('id')->toBase();
        foreach ($q->lazy(5000) as $v) {
            $n = Text::nameKey($v->name);
            if ($n === '') continue;
            $keys[$n . '|' . Text::nameKey($v->relative_name)][] = [$v->id, $v->age];
        }
        $groups = [];
        foreach ($keys as $members) {
            if (count($members) < 2) continue;
            if ($sameAge) {
                $ages = array_filter(array_column($members, 1), fn ($a) => $a !== null);
                if ($ages && max($ages) - min($ages) > 3) {
                    // split into clusters of similar age
                    usort($members, fn ($x, $y) => ($x[1] ?? 0) <=> ($y[1] ?? 0));
                    $cl = [];
                    foreach ($members as $m) {
                        $last = $cl ? $cl[count($cl) - 1] : null;
                        if ($last && ($m[1] === null || $last[count($last) - 1][1] === null || $m[1] - $last[count($last) - 1][1] <= 3)) $cl[count($cl) - 1][] = $m;
                        else $cl[] = [$m];
                    }
                    foreach ($cl as $c) if (count($c) > 1) $groups[] = array_column($c, 0);
                    continue;
                }
            }
            $groups[] = array_column($members, 0);
        }
        unset($keys);
        if ($booth) {
            $inBooth = array_flip(Voter::where('assembly_no', $assembly)->where('booth_no', $booth)->pluck('id')->all());
            $groups = array_values(array_filter($groups, fn ($g) => array_intersect_key(array_flip($g), $inBooth)));
        }
        $total = count($groups);
        $page = array_slice($groups, $offset, $limit);
        $models = Voter::whereIn('id', array_merge([0], ...$page))->get()->keyBy('id');
        return array_map(fn ($g) => array_values(array_filter(array_map(fn ($id) => $models[$id] ?? null, $g))), $page);
    }

    /** Merges $drop into $keep. Returns the merge id. */
    public function merge(Voter $keep, Voter $drop, ?string $by): int
    {
        if ($keep->id === $drop->id || $keep->assembly_no !== $drop->assembly_no || $drop->deleted) throw new \InvalidArgumentException('cannot merge');
        return DB::transaction(function () use ($keep, $drop, $by) {
            $before = ['keep' => [], 'after' => [], 'drop' => ['deleted' => (bool) $drop->deleted], 'moved' => []];
            foreach (self::fields() as $f) {
                $kv = $keep->getRawOriginal($f); $dv = $drop->getRawOriginal($f);
                if (($kv === null || $kv === '') && $dv !== null && $dv !== '') {
                    $before['keep'][$f] = $kv;
                    $before['after'][$f] = $dv;
                    $keep->setRawAttributes(array_merge($keep->getAttributes(), [$f => $dv]));
                }
            }
            if ($before['keep']) $keep->save();
            foreach (self::LINKED as $table) {
                if (!Schema::hasTable($table)) continue;
                $q = DB::table($table)->where('voter_id', $drop->id);
                if ($table === 'poll_marks') $q->whereNotIn('election_id', DB::table('poll_marks')->where('voter_id', $keep->id)->pluck('election_id')->all() ?: [0]);
                $ids = $q->pluck('id')->all();
                if ($ids) { DB::table($table)->whereIn('id', $ids)->update(['voter_id' => $keep->id]); $before['moved'][$table] = $ids; }
            }
            $drop->deleted = true;
            $drop->save();
            $id = DB::table('voter_merges')->insertGetId(['keep_id' => $keep->id, 'drop_id' => $drop->id, 'assembly_no' => $keep->assembly_no,
                'before' => json_encode($before), 'by_user' => $by, 'at' => now()]);
            $this->audit->log('UPDATE', 'VOTER', $keep->id, "merged duplicate {$drop->epic_no} (#{$drop->id}) into {$keep->epic_no}: "
                . (implode(', ', array_keys($before['keep'])) ?: 'no fields') . ' copied', $by);
            return $id;
        });
    }

    /** Undoes a merge: the duplicate comes back, moved rows return, copied fields are cleared unless changed since. Returns fields left as they are. */
    public function undo(int $mergeId, ?string $by): array
    {
        return DB::transaction(function () use ($mergeId, $by) {
            $m = DB::table('voter_merges')->lockForUpdate()->find($mergeId);
            if (!$m || $m->undone_at) throw new \InvalidArgumentException('not found');
            $b = json_decode($m->before, true);
            $keep = Voter::withoutGlobalScopes()->find($m->keep_id); $drop = Voter::withoutGlobalScopes()->find($m->drop_id);
            $kept = [];
            if ($keep) {
                $attrs = $keep->getAttributes(); $dirty = false;
                foreach ($b['keep'] as $f => $old) {
                    if ((string) $keep->getRawOriginal($f) !== (string) ($b['after'][$f] ?? '')) { $kept[] = $f; continue; }   // changed after the merge
                    $attrs[$f] = $old; $dirty = true;
                }
                if ($dirty) { $keep->setRawAttributes($attrs); $keep->save(); }
            }
            foreach ($b['moved'] as $table => $ids) {
                if (Schema::hasTable($table)) DB::table($table)->whereIn('id', $ids)->where('voter_id', $m->keep_id)->update(['voter_id' => $m->drop_id]);
            }
            if ($drop) { $drop->deleted = (bool) ($b['drop']['deleted'] ?? false); $drop->save(); }
            DB::table('voter_merges')->where('id', $m->id)->update(['undone_at' => now()]);
            $this->audit->log('UPDATE', 'VOTER', $m->keep_id, "undid merge #{$m->id} (duplicate #{$m->drop_id} restored)", $by);
            return $kept;
        });
    }
}
