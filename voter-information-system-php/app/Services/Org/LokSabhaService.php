<?php

namespace App\Services\Org;

use App\Services\Reports\AnalyticsService;
use App\Support\SharedCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lok Sabha overview: one row per assembly segment (roll, survey, organisation, grievances) with a
 * red / amber / green flag, and the constituency total.
 */
class LokSabhaService
{
    /** Flag thresholds: green needs both, red when either is below its floor. */
    public const GREEN_SURVEY = 50, GREEN_PRESIDENT = 80, RED_SURVEY = 10, RED_PRESIDENT = 30;

    public function __construct(private OrganisationService $org) {}

    private static function pct($a, $b): float { return $b ? round(1000 * $a / $b) / 10 : 0.0; }

    /** @param array $assemblies [no => name] the user may see */
    public function overview(array $assemblies): array
    {
        $nos = array_keys($assemblies);
        if (!$nos) return ['rows' => [], 'total' => null];
        $ttl = max(0, (int) config('voters.lookup_cache_seconds', 300));
        $voters = SharedCache::remember('vis.ls.voters.' . md5(json_encode($nos)), $ttl, function () use ($nos) {
            $out = [];
            foreach (DB::table('voters')->whereIn('assembly_no', $nos)->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))
                         ->selectRaw("assembly_no, count(*) as n, count(distinct booth_no) as booths,
                            sum(case when gender = 'FEMALE' then 1 else 0 end) as f,
                            sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as mobile,
                            sum(case when political_status is not null and political_status <> '' then 1 else 0 end) as surveyed,
                            sum(case when " . AnalyticsService::SENTIMENT_SQL . " = 'S' then 1 else 0 end) as support,
                            sum(case when age between 18 and 19 then 1 else 0 end) as first")
                         ->groupBy('assembly_no')->get() as $r) $out[(int) $r->assembly_no] = (array) $r;
            return $out;
        });
        $griev = [];
        if (Schema::hasTable('grievances')) {
            foreach (DB::table('grievances')->whereIn('assembly_no', $nos)->whereIn('status', ['OPEN', 'IN_PROGRESS'])->selectRaw('assembly_no, count(*) as c')
                         ->groupBy('assembly_no')->get() as $r) $griev[(int) $r->assembly_no] = (int) $r->c;
        }
        $org = OrganisationService::available() ? $this->org->summary($nos) : [];
        $rows = []; $T = array_fill_keys(['n', 'booths', 'f', 'mobile', 'surveyed', 'support', 'first', 'griev', 'withPresident', 'withPanna', 'pannaVoters', 'workers', 'mandals'], 0);
        foreach ($assemblies as $a => $name) {
            $v = $voters[$a] ?? ['n' => 0, 'booths' => 0, 'f' => 0, 'mobile' => 0, 'surveyed' => 0, 'support' => 0, 'first' => 0];
            $o = $org[$a] ?? ['incharge' => collect(), 'withPresident' => 0, 'withPanna' => 0, 'pannaVoters' => 0, 'workers' => 0, 'mandals' => 0, 'booths' => 0];
            $n = (int) $v['n']; $b = (int) $v['booths'];
            $survey = self::pct($v['surveyed'], $n); $pres = self::pct($o['withPresident'], $b);
            $flag = !$n ? 'none' : (($survey < self::RED_SURVEY || $pres < self::RED_PRESIDENT) ? 'red'
                : (($survey >= self::GREEN_SURVEY && $pres >= self::GREEN_PRESIDENT) ? 'green' : 'amber'));
            $rows[] = ['no' => $a, 'name' => $name, 'voters' => $n, 'booths' => $b, 'womenPct' => self::pct($v['f'], $n), 'first' => (int) $v['first'],
                'mobilePct' => self::pct($v['mobile'], $n), 'surveyPct' => $survey, 'supportPct' => self::pct($v['support'], (int) $v['surveyed']),
                'presidentPct' => $pres, 'pannaPct' => self::pct($o['pannaVoters'], $n), 'withPresident' => $o['withPresident'], 'mandals' => $o['mandals'],
                'workers' => $o['workers'], 'incharge' => $o['incharge'], 'griev' => $griev[$a] ?? 0, 'flag' => $flag];
            foreach (['n', 'booths', 'f', 'mobile', 'surveyed', 'support', 'first'] as $k) $T[$k] += (int) $v[$k];
            foreach (['withPresident', 'withPanna', 'pannaVoters', 'workers', 'mandals'] as $k) $T[$k] += (int) ($o[$k] ?? 0);
            $T['griev'] += $griev[$a] ?? 0;
        }
        $total = ['voters' => $T['n'], 'booths' => $T['booths'], 'womenPct' => self::pct($T['f'], $T['n']), 'first' => $T['first'],
            'mobilePct' => self::pct($T['mobile'], $T['n']), 'surveyPct' => self::pct($T['surveyed'], $T['n']), 'supportPct' => self::pct($T['support'], $T['surveyed']),
            'presidentPct' => self::pct($T['withPresident'], $T['booths']), 'pannaPct' => self::pct($T['pannaVoters'], $T['n']), 'withPresident' => $T['withPresident'],
            'mandals' => $T['mandals'], 'workers' => $T['workers'], 'griev' => $T['griev'],
            'segments' => count($rows), 'withData' => count(array_filter($rows, fn ($r) => $r['voters'] > 0)),
            'flags' => array_count_values(array_column($rows, 'flag'))];
        return ['rows' => $rows, 'total' => $total];
    }
}
