<?php

namespace App\Services\Reports;

use App\Models\Election;
use App\Services\Results\ResultService;
use Illuminate\Support\Facades\DB;

/** Data for the campaign dashboard charts (same JSON shape as the Java version, used by public/js/dashboard.js). */
class DashboardService
{
    public function __construct(private ReportService $reports, private ResultService $results) {}

    private static function pct($p, $w): float { return $w ? round(1000 * $p / $w) / 10 : 0.0; }
    private static function series(string $name, string $role, array $data): array { return compact('name', 'role', 'data'); }

    public function build(int $a, ?Election $e, ?string $focusParty, ?int $ward = null): array
    {
        $this->reports->ward = $ward;   // booth figures of the chosen ward only
        $w = fn ($q) => $q->when($ward !== null, fn ($q) => $q->where('ward_no', $ward));
        $booths = []; $male = []; $female = []; $other = []; $young = []; $mobile = []; $boothVoters = [];
        $total = $withMobile = $youngAll = $seniorAll = $otherAll = 0;
        foreach ($this->reports->boothRows($a) as $x) {
            $booths[] = (string) $x->booth_no;
            $boothVoters[$x->booth_no] = (int) $x->total;
            $male[] = (int) $x->male; $female[] = (int) $x->female; $other[] = (int) $x->other;
            $mobile[] = self::pct($x->with_mobile, $x->total); $young[] = (int) $x->young;
            $total += $x->total; $withMobile += $x->with_mobile; $youngAll += $x->young; $seniorAll += $x->senior; $otherAll += $x->other;
        }
        $bs = [self::series(t('gender.MALE'), 'male', $male), self::series(t('gender.FEMALE'), 'female', $female)];
        if ($otherAll) $bs[] = self::series(t('gender.OTHER'), 'other', $other);
        $out = [
            'boothVoters' => ['labels' => $booths, 'series' => $bs],
            'mobile' => ['labels' => $booths, 'series' => [self::series(t('report.mobilePct'), 'single', $mobile)]],
            'young' => ['labels' => $booths, 'series' => [self::series(t('dash.tile.young'), 'single', $young)]],
        ];
        $groups = [[18, 25], [26, 35], [36, 45], [46, 60], [61, 200]];
        $ag = array_fill(0, 5, ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0]);
        foreach ($w(DB::table('voters')->selectRaw('age, gender, count(*) as c')->where('assembly_no', $a))->whereNotNull('age')->groupBy('age', 'gender')->get() as $x) {
            foreach ($groups as $i => $g) if ($x->age >= $g[0] && $x->age <= $g[1]) { $ag[$i][$x->gender] += (int) $x->c; break; }
        }
        $as = [self::series(t('gender.MALE'), 'male', array_column($ag, 'MALE')), self::series(t('gender.FEMALE'), 'female', array_column($ag, 'FEMALE'))];
        if ($otherAll) $as[] = self::series(t('gender.OTHER'), 'other', array_column($ag, 'OTHER'));
        $out['ageGender'] = ['labels' => ['18-25', '26-35', '36-45', '46-60', '60+'], 'series' => $as];

        $sizes = array_fill(0, 6, 0); $houses = $big = $housed = 0;
        $rows = $w(DB::table('voters')->selectRaw('count(*) as c')->where('assembly_no', $a))->whereNotNull('house_no')->where('house_no', '<>', '')
            ->groupBy(DB::raw('lower(house_no)'), DB::raw("lower(coalesce(address, ''))"), DB::raw("lower(coalesce(village_ward, ''))"))->get();
        foreach ($rows as $x) { $n = (int) $x->c; $sizes[min($n, 6) - 1]++; $houses++; $housed += $n; if ($n >= 5) $big++; }
        $out['household'] = ['labels' => ['1', '2', '3', '4', '5', '6+'], 'series' => [self::series(t('dash.houses'), 'single', $sizes)]];

        $tiles = [
            'list.totalVoters' => $total, 'report.booths' => count($booths),
            'dash.tile.young' => $youngAll . ' (' . self::pct($youngAll, $total) . '%)',
            'dash.tile.senior' => $seniorAll . ' (' . self::pct($seniorAll, $total) . '%)',
            'dash.tile.mobile' => $withMobile . ' (' . self::pct($withMobile, $total) . '%)',
            'dash.tile.households' => $houses, 'dash.tile.avgHousehold' => $houses ? number_format($housed / $houses, 1) : '0',
            'dash.tile.bigHouseholds' => $big,
        ];

        $el = null;
        if ($e) {
            $c = $this->results->contest($e, $a, $ward);
            if ($c['total_votes'] > 0) {
                $parties = array_values(array_unique(array_map(fn ($t) => $t['candidate']->partyShort(), $c['totals'])));
                $focus = $focusParty && in_array($focusParty, $parties, true) ? $focusParty : $c['winner']['candidate']->partyShort();
                $focusIds = [];
                $pl = []; $ps = [];
                foreach ($c['totals'] as $t) {
                    $pl[] = $t['candidate']->partyShort() . ' - ' . $t['candidate']->name;
                    $ps[] = round($t['share'], 1);
                    if ($t['candidate']->partyShort() === $focus) $focusIds[$t['candidate']->id] = true;
                }
                $bl = []; $margin = []; $turnout = []; $ahead = 0; $focusVotes = 0;
                foreach ($c['booths'] as $b) {
                    $mine = 0; $rival = 0;
                    foreach ($b['votes'] as $cid => $v) { if (isset($focusIds[$cid])) $mine += $v; else $rival = max($rival, $v); }
                    $bl[] = (string) $b['booth_no']; $margin[] = $mine - $rival; if ($mine > $rival) $ahead++;
                    $turnout[] = self::pct($b['total'], $boothVoters[$b['booth_no']] ?? 0);
                }
                foreach ($c['totals'] as $t) if (isset($focusIds[$t['candidate']->id])) $focusVotes += $t['votes'];
                $el = ['name' => $e->name, 'parties' => $parties, 'focusParty' => $focus,
                    'partyShare' => ['labels' => $pl, 'series' => [self::series(t('result.voteSharePct'), 'single', $ps)]],
                    'margin' => ['labels' => $bl, 'series' => [self::series(t('result.margin'), 'diverging', $margin)]],
                    'turnout' => ['labels' => $bl, 'series' => [self::series(t('result.turnoutPct'), 'single', $turnout)]]];
                $tiles['dash.tile.share'] = self::pct($focusVotes, $c['total_votes']) . "% ($focus)";
                $tiles['dash.tile.boothsAhead'] = $ahead . ' / ' . count($c['booths']);
            }
        }
        $out['election'] = $el;
        $out['tiles'] = array_map(fn ($k, $v) => ['label' => t($k), 'value' => $v], array_keys($tiles), $tiles);
        $out['text'] = ['booth' => t('col.boothNo'), 'voters' => t('dash.voters'), 'houses' => t('dash.houses'), 'lead' => t('dash.lead'),
            'trail' => t('dash.trail'), 'pct' => '%', 'ageGroup' => t('report.ageGroup'), 'table' => t('dash.showTable'), 'chart' => t('dash.showChart'),
            'party' => t('result.party')];
        return $out;
    }
}
