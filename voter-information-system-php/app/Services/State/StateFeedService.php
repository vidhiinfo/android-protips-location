<?php

namespace App\Services\State;

use App\Services\Reports\AnalyticsService;
use App\Support\SharedCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Division side of the state summary: totals per assembly for the state server (STATE_FEED_TOKEN).
 * Only counts leave the installation: no names, EPIC numbers, mobiles or any other personal data.
 */
class StateFeedService
{
    public const VERSION = 1;

    public function build(): array
    {
        $ttl = max(30, (int) config('voters.state.feed_seconds', 120));
        return SharedCache::remember('vis.state.feed', $ttl, fn () => $this->compute());
    }

    private function compute(): array
    {
        $alive = fn ($q) => $q->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'));
        $a = [];
        $blank = fn ($no) => ['assembly_no' => (int) $no, 'name' => null, 'voters' => 0, 'male' => 0, 'female' => 0, 'other' => 0, 'booths' => 0,
            'mobile' => 0, 'surveyed' => 0, 'S' => 0, 'N' => 0, 'O' => 0, 'U' => 0, 'workers' => 0, 'panna' => 0, 'grievances' => 0,
            'voted' => 0, 'supportersVoted' => 0];
        // one pass over the voters, grouped by assembly and political status (few distinct values), classified like the analytics
        foreach ($alive(DB::table('voters'))->selectRaw("assembly_no, political_status, count(*) as n,
                    sum(case when gender = 'MALE' then 1 else 0 end) as male, sum(case when gender = 'FEMALE' then 1 else 0 end) as female,
                    sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as mob")
                     ->groupBy('assembly_no', 'political_status')->get() as $r) {
            $x = &$a[$r->assembly_no]; $x ??= $blank($r->assembly_no);
            $x['voters'] += (int) $r->n; $x['male'] += (int) $r->male; $x['female'] += (int) $r->female; $x['mobile'] += (int) $r->mob;
            $st = AnalyticsService::sentimentOf($r->political_status);
            $x[$st] += (int) $r->n;
            if (trim((string) $r->political_status) !== '') $x['surveyed'] += (int) $r->n;
            unset($x);
        }
        foreach ($a as $no => &$x) $x['other'] = $x['voters'] - $x['male'] - $x['female'];
        unset($x);
        foreach ($alive(DB::table('voters'))->selectRaw('assembly_no, count(distinct booth_no) as b')->groupBy('assembly_no')->get() as $r) {
            if (isset($a[$r->assembly_no])) $a[$r->assembly_no]['booths'] = (int) $r->b;
        }
        $names = DB::table('assemblies')->pluck('name', 'assembly_no')->all();
        foreach ($a as $no => &$x) $x['name'] = $names[$no] ?? DB::table('voters')->where('assembly_no', $no)->value('assembly_name');
        unset($x);
        if (Schema::hasTable('workers')) {
            foreach (DB::table('workers')->where('active', true)->selectRaw("assembly_no, count(*) as n, sum(case when role = 'PANNA_PRAMUKH' then 1 else 0 end) as p")
                         ->groupBy('assembly_no')->get() as $r) {
                if (isset($a[$r->assembly_no])) { $a[$r->assembly_no]['workers'] = (int) $r->n; $a[$r->assembly_no]['panna'] = (int) $r->p; }
            }
        }
        if (Schema::hasTable('grievances')) {
            foreach (DB::table('grievances')->whereIn('status', ['OPEN', 'IN_PROGRESS'])->selectRaw('assembly_no, count(*) as n')->groupBy('assembly_no')->get() as $r) {
                if (isset($a[$r->assembly_no])) $a[$r->assembly_no]['grievances'] = (int) $r->n;
            }
        }
        $e = DB::table('elections')->where('current', true)->first();
        if ($e && Schema::hasTable('poll_marks')) {
            foreach (DB::table('poll_marks as pm')->join('voters as v', 'v.id', '=', 'pm.voter_id')->where('pm.election_id', $e->id)
                         ->selectRaw('v.assembly_no, v.political_status, count(*) as n')->groupBy('v.assembly_no', 'v.political_status')->get() as $r) {
                if (!isset($a[$r->assembly_no])) continue;
                $a[$r->assembly_no]['voted'] += (int) $r->n;
                if (AnalyticsService::sentimentOf($r->political_status) === 'S') $a[$r->assembly_no]['supportersVoted'] += (int) $r->n;
            }
        }
        ksort($a);
        return ['version' => self::VERSION, 'name' => (string) (config('voters.state.feed_name') ?: config('voters.branding.app_name') ?: config('app.name')),
            'generatedAt' => now()->toIso8601String(), 'election' => $e ? ['name' => $e->name, 'pollDate' => $e->poll_date] : null,
            'assemblies' => array_values($a)];
    }
}
