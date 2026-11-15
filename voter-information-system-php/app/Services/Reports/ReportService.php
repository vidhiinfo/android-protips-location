<?php

namespace App\Services\Reports;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\Election;
use App\Models\OptOut;
use App\Models\Voter;
use App\Services\Results\ResultService;
use App\Services\Voters\VoterFilter;
use App\Services\Voters\VoterService;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/** The 13 reports of the Reports menu. */
class ReportService
{
    public const SECTIONS = [
        'voters' => ['assembly-summary', 'booth-summary', 'village-summary', 'age-gender', 'category-summary'],
        'outreach' => ['first-time-voters', 'senior-voters', 'large-households', 'phone-bank'],
        'election' => ['results-summary', 'party-performance', 'booth-priority'],
        'quality' => ['duplicate-voters'],
    ];
    /** Sensitive personal data (social category): needs REPORT_SENSITIVE. */
    public const ADMIN_ONLY = ['category-summary'];
    public const SWING_PCT = 10.0;

    public function __construct(private ResultService $results, private VoterService $voters) {}

    public static function all(): array { return array_merge(...array_values(self::SECTIONS)); }

    public function build(string $id, ?int $assemblyNo, ?int $boothNo, ?Election $e, ?string $scope = null, ?string $party = null): Report
    {
        return match ($id) {
            'assembly-summary' => $this->assemblySummary($assemblyNo),
            'booth-summary' => $this->boothSummary($assemblyNo),
            'village-summary' => $this->villageSummary($assemblyNo),
            'age-gender' => $this->ageGender($assemblyNo, $boothNo),
            'category-summary' => $this->categorySummary($assemblyNo),
            'first-time-voters' => $this->voterList($id, $assemblyNo, $boothNo, 18, 21, false),
            'senior-voters' => $this->voterList($id, $assemblyNo, $boothNo, 85, null, false),
            'phone-bank' => $this->voterList($id, $assemblyNo, $boothNo, null, null, true),
            'large-households' => $this->largeHouseholds($assemblyNo),
            'results-summary' => $this->resultsSummary($e, $assemblyNo),
            'party-performance' => $this->partyPerformance($e, $assemblyNo),
            'booth-priority' => $this->boothPriority($e, $assemblyNo, $party),
            'duplicate-voters' => $this->duplicates($assemblyNo, $scope === 'ward'),
        };
    }

    private function h(string ...$keys): array { return array_map(fn ($k) => t($k), $keys); }
    private static function pct($part, $whole): float { return $whole ? round(10000 * $part / $whole) / 100 : 0.0; }
    private function assemblyName(int $no, ?string $fallback = ''): string { return Assembly::where('assembly_no', $no)->value('name') ?? (string) $fallback; }

    private function scope(?int $a, ?int $b): string
    {
        $s = [];
        if ($a !== null) $s[] = t('col.assemblyNo') . " $a " . $this->assemblyName($a);
        if ($b !== null) $s[] = t('col.boothNo') . " $b";
        return $s ? trim(implode(', ', $s)) : t('report.allAssemblies');
    }

    private const COUNTS = "count(*) as total,
        sum(case when gender = 'MALE' then 1 else 0 end) as male,
        sum(case when gender = 'FEMALE' then 1 else 0 end) as female,
        sum(case when gender = 'OTHER' then 1 else 0 end) as other,
        sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as with_mobile,
        sum(case when age between 18 and 25 then 1 else 0 end) as young,
        sum(case when age >= 60 then 1 else 0 end) as senior";

    // ---------------------------------------------------------------- voter analysis
    public function assemblySummary(?int $only): Report
    {
        $r = new Report(t('report.assembly-summary'), $this->h('col.assemblyNo', 'col.assemblyName', 'report.booths', 'report.total',
            'gender.MALE', 'gender.FEMALE', 'gender.OTHER', 'report.withMobile', 'report.mobilePct', 'report.age18to25', 'report.age60plus'));
        $tot = array_fill(0, 8, 0);
        $q = DB::table('voters')->selectRaw('assembly_no, max(assembly_name) as aname, count(distinct booth_no) as booths, ' . self::COUNTS)
            ->groupBy('assembly_no')->orderBy('assembly_no');
        if ($only !== null) $q->where('assembly_no', $only);
        foreach ($q->get() as $x) {
            $vals = [(int) $x->booths, (int) $x->total, (int) $x->male, (int) $x->female, (int) $x->other, (int) $x->with_mobile, (int) $x->young, (int) $x->senior];
            $r->add([(int) $x->assembly_no, $this->assemblyName((int) $x->assembly_no, $x->aname), $vals[0], $vals[1], $vals[2], $vals[3], $vals[4],
                $vals[5], self::pct($vals[5], $vals[1]), $vals[6], $vals[7]]);
            foreach ($vals as $i => $v) $tot[$i] += $v;
        }
        $r->totals = [t('report.total'), '', $tot[0], $tot[1], $tot[2], $tot[3], $tot[4], $tot[5], self::pct($tot[5], $tot[1]), $tot[6], $tot[7]];
        return $r;
    }

    public function boothRows(?int $a)
    {
        return DB::table('voters')->selectRaw('assembly_no, booth_no, max(booth_name) as bname, ' . self::COUNTS)
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->groupBy('assembly_no', 'booth_no')->orderBy('assembly_no')->orderBy('booth_no')->get();
    }

    public function boothSummary(?int $a): Report
    {
        $r = (new Report(t('report.booth-summary'), $this->h('col.assemblyNo', 'col.boothNo', 'col.boothName', 'report.total', 'gender.MALE',
            'gender.FEMALE', 'gender.OTHER', 'report.genderRatio', 'report.withMobile', 'report.mobilePct', 'report.age18to25', 'report.age60plus')))
            ->subtitle($this->scope($a, null));
        $tot = array_fill(0, 7, 0);
        $names = Booth::all()->mapWithKeys(fn ($b) => [$b->assembly_no . '/' . $b->booth_no => $b->name])->all();
        foreach ($this->boothRows($a) as $x) {
            $v = [(int) $x->total, (int) $x->male, (int) $x->female, (int) $x->other, (int) $x->with_mobile, (int) $x->young, (int) $x->senior];
            $r->add([(int) $x->assembly_no, (int) $x->booth_no, $names[$x->assembly_no . '/' . $x->booth_no] ?? (string) $x->bname, $v[0], $v[1], $v[2], $v[3],
                $v[1] ? (int) round(1000 * $v[2] / $v[1]) : 0, $v[4], self::pct($v[4], $v[0]), $v[5], $v[6]]);
            foreach ($v as $i => $n) $tot[$i] += $n;
        }
        $r->totals = [t('report.total'), '', '', $tot[0], $tot[1], $tot[2], $tot[3], $tot[1] ? (int) round(1000 * $tot[2] / $tot[1]) : 0, $tot[4],
            self::pct($tot[4], $tot[0]), $tot[5], $tot[6]];
        return $r;
    }

    public function villageSummary(?int $a): Report
    {
        $r = (new Report(t('report.village-summary'), $this->h('col.villageWard', 'report.total', 'gender.MALE', 'gender.FEMALE', 'gender.OTHER',
            'report.withMobile', 'report.mobilePct', 'report.age18to25', 'report.age60plus')))->subtitle($this->scope($a, null));
        $rows = DB::table('voters')->selectRaw("coalesce(village_ward, '-') as vw, " . self::COUNTS)
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->groupBy('village_ward')->orderByDesc('total')->get();
        foreach ($rows as $x) $r->add([$x->vw, (int) $x->total, (int) $x->male, (int) $x->female, (int) $x->other, (int) $x->with_mobile,
            self::pct($x->with_mobile, $x->total), (int) $x->young, (int) $x->senior]);
        return $r;
    }

    public function ageGender(?int $a, ?int $b): Report
    {
        $groups = [[18, 25], [26, 35], [36, 45], [46, 60], [61, 80], [81, 200]];
        $counts = array_fill(0, count($groups) + 1, ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0]);
        $rows = DB::table('voters')->selectRaw('age, gender, count(*) as c')->when($a !== null, fn ($q) => $q->where('assembly_no', $a))
            ->when($b !== null, fn ($q) => $q->where('booth_no', $b))->groupBy('age', 'gender')->get();
        foreach ($rows as $x) {
            $idx = count($groups);
            if ($x->age !== null) foreach ($groups as $i => $g) if ($x->age >= $g[0] && $x->age <= $g[1]) { $idx = $i; break; }
            $counts[$idx][$x->gender] += (int) $x->c;
        }
        $r = (new Report(t('report.age-gender'), $this->h('report.ageGroup', 'gender.MALE', 'gender.FEMALE', 'gender.OTHER', 'report.total', 'report.sharePct')))
            ->subtitle($this->scope($a, $b));
        $grand = array_sum(array_map('array_sum', $counts));
        $tot = ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0];
        foreach ($counts as $i => $c) {
            $t = array_sum($c);
            if ($i === count($groups) && $t === 0) continue;
            $label = $i === count($groups) ? t('report.ageUnknown') : ($groups[$i][1] >= 200 ? $groups[$i][0] . '+' : $groups[$i][0] . '-' . $groups[$i][1]);
            $r->add([$label, $c['MALE'], $c['FEMALE'], $c['OTHER'], $t, self::pct($t, $grand)]);
            foreach ($c as $g => $n) $tot[$g] += $n;
        }
        $r->totals = [t('report.total'), $tot['MALE'], $tot['FEMALE'], $tot['OTHER'], $grand, $grand ? 100.0 : 0.0];
        return $r;
    }

    public function categorySummary(?int $a): Report
    {
        $r = (new Report(t('report.category-summary'), $this->h('col.assemblyNo', 'col.boothNo', 'col.boothName', 'report.cat.GEN', 'report.cat.OBC',
            'report.cat.SC', 'report.cat.ST', 'report.cat.other', 'report.cat.none', 'report.total')))
            ->subtitle($this->scope($a, null) . ' | ' . t('report.sensitiveNote'));
        $rows = DB::table('voters')->selectRaw("assembly_no, booth_no, upper(trim(coalesce(category, ''))) as cat, count(*) as c")
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->groupBy('assembly_no', 'booth_no', DB::raw("upper(trim(coalesce(category, '')))"))
            ->orderBy('assembly_no')->orderBy('booth_no')->get();
        $by = [];
        foreach ($rows as $x) {
            $k = $x->assembly_no . '/' . $x->booth_no;
            $by[$k] ??= ['a' => (int) $x->assembly_no, 'b' => (int) $x->booth_no, 'v' => array_fill(0, 6, 0)];
            $idx = match ($x->cat) { 'GEN', 'GENERAL', 'UR' => 0, 'OBC' => 1, 'SC' => 2, 'ST' => 3, '' => 5, default => 4 };
            $by[$k]['v'][$idx] += (int) $x->c;
        }
        $names = Booth::all()->mapWithKeys(fn ($b) => [$b->assembly_no . '/' . $b->booth_no => $b->name])->all();
        $tot = array_fill(0, 6, 0);
        foreach ($by as $k => $x) {
            $r->add(array_merge([$x['a'], $x['b'], $names[$k] ?? ''], $x['v'], [array_sum($x['v'])]));
            foreach ($x['v'] as $i => $n) $tot[$i] += $n;
        }
        $g = array_sum($tot);
        $r->totals = array_merge([t('report.total'), '', ''], $tot, [$g]);
        $r->add(array_merge(['%', '', ''], array_map(fn ($n) => self::pct($n, $g), $tot), [$g ? 100.0 : 0.0]));
        return $r;
    }

    // ---------------------------------------------------------------- outreach
    private function filter(?int $a, ?int $b): VoterFilter
    {
        $f = new VoterFilter();
        $f->set('assemblyNo', $a);
        $f->set('boothNo', $b);
        $f->sort = 'boothNo';
        return $f;
    }

    public function voterList(string $id, ?int $a, ?int $b, ?int $min, ?int $max, bool $phoneBank): Report
    {
        $f = $this->filter($a, $b);
        $f->set('ageMin', $min);
        $f->set('ageMax', $max);
        if ($phoneBank) $f->set('hasMobile', 'true');
        $blocked = $phoneBank ? OptOut::whereNull('channel')->pluck('mobile')->flip()->all() : [];
        $r = (new Report(t('report.' . $id), $this->h('col.assemblyNo', 'col.boothNo', 'col.serialNo', 'col.epicNo', 'col.name', 'col.nameLocal',
            'col.relativeName', 'col.gender', 'col.age', 'col.houseNo', 'col.address', 'col.mobile')))->subtitle($this->scope($a, $b));
        $skipped = 0;
        foreach ($this->voters->searchAll($f) as $v) {
            if ($phoneBank && isset($blocked[$v->mobile])) { $skipped++; continue; }
            $r->add([$v->assembly_no, $v->booth_no, $v->serial_no, $v->epic_no, $v->name, $v->name_local, $v->relative_name, $v->genderLabel(),
                $v->age, $v->house_no, $v->fullAddress(), $v->mobile]);
        }
        $r->subtitle .= ' | ' . t('report.count', count($r->rows)) . ($phoneBank && $skipped ? ' | ' . t('report.optOutsExcluded', $skipped) : '');
        return $r;
    }

    public function largeHouseholds(?int $a): Report
    {
        $r = (new Report(t('report.large-households'), $this->h('col.assemblyNo', 'col.boothNo', 'col.houseNo', 'col.address', 'col.villageWard',
            'report.votersInHouse', 'report.withMobile', 'report.members')))->subtitle($this->scope($a, null));
        $houses = [];
        foreach ($this->voters->searchAll($this->filter($a, null)) as $v) {
            if (!$v->house_no) continue;
            $k = $v->assembly_no . '|' . mb_strtolower(trim($v->house_no)) . '|' . mb_strtolower(trim((string) $v->address)) . '|' . mb_strtolower(trim((string) $v->village_ward));
            $houses[$k][] = $v;
        }
        $houses = array_filter($houses, fn ($l) => count($l) >= 5);
        usort($houses, fn ($x, $y) => count($y) <=> count($x));
        foreach ($houses as $l) {
            $v = $l[0];
            $r->add([$v->assembly_no, $v->booth_no, $v->house_no, $v->address, $v->village_ward, count($l),
                count(array_filter($l, fn ($x) => $x->mobile)), implode(', ', array_map(fn ($x) => $x->name . ($x->age ? " ({$x->age})" : ''), $l))]);
        }
        $r->subtitle .= ' | ' . t('report.count', count($r->rows));
        return $r;
    }

    // ---------------------------------------------------------------- election analysis
    public function resultsSummary(?Election $e, ?int $only): Report
    {
        $r = new Report(t('report.results-summary'), $this->h('col.assemblyNo', 'col.assemblyName', 'result.winner', 'result.party', 'result.votes',
            'result.runnerUp', 'result.party', 'result.margin', 'result.totalVotes', 'result.turnoutPct', 'result.boothsReported'));
        if (!$e) return $r->subtitle(t('result.noElection'));
        $r->subtitle($e->name);
        foreach ($this->results->electionContests($e, $only) as $c) {
            $w = $c['winner']; $ru = $c['runner_up'];
            $r->add([$c['assembly_no'], $c['assembly_name'], $w ? $w['candidate']->name : '-', $w ? $w['candidate']->partyShort() : '', $w['votes'] ?? 0,
                $ru ? $ru['candidate']->name : '-', $ru ? $ru['candidate']->partyShort() : '', $c['margin'], $c['total_votes'],
                round($c['turnout'], 2), $c['booths_reported'] . '/' . $c['booths_total']]);
        }
        return $r;
    }

    public function partyPerformance(?Election $e, ?int $only): Report
    {
        $r = new Report(t('report.party-performance'), $this->h('result.party', 'result.contested', 'result.won', 'result.votes', 'result.voteSharePct'));
        if (!$e) return $r->subtitle(t('result.noElection'));
        $r->subtitle($e->name);
        $m = [];
        $all = 0;
        foreach ($this->results->electionContests($e, $only) as $c) {
            foreach ($c['totals'] as $t) {
                $p = $t['candidate']->partyShort();
                $m[$p] ??= [0, 0, 0];
                $m[$p][0]++;
                if ($c['winner'] && $c['winner']['candidate']->id === $t['candidate']->id) $m[$p][1]++;
                $m[$p][2] += $t['votes'];
            }
            $all += $c['total_votes'];
        }
        uasort($m, fn ($x, $y) => [$y[1], $y[2]] <=> [$x[1], $x[2]]);
        foreach ($m as $p => $x) $r->add([$p, $x[0], $x[1], $x[2], self::pct($x[2], $all)]);
        $r->totals = [t('report.total'), array_sum(array_column($m, 0)), array_sum(array_column($m, 1)), $all, $all ? 100.0 : 0.0];
        return $r;
    }

    public function boothPriority(?Election $e, ?int $a, ?string $party): Report
    {
        $r = new Report(t('report.booth-priority'), $this->h('col.assemblyNo', 'col.boothNo', 'col.boothName', 'report.priority', 'dash.focusParty',
            'result.votes', 'report.topRival', 'result.votes', 'result.margin', 'report.marginPct', 'result.turnoutPct', 'dash.voters'));
        if (!$e) return $r->subtitle(t('result.noElection'));
        $r->subtitle($e->name . ' | ' . t('report.priorityNote', (int) self::SWING_PCT));
        $rows = [];
        foreach ($this->results->electionContests($e, $a) as $c) {
            if (!$c['winner']) continue;
            $focus = $party ?: $c['winner']['candidate']->partyShort();
            $partyOf = [];
            foreach ($c['totals'] as $t) $partyOf[$t['candidate']->id] = $t['candidate']->partyShort();
            $electors = [];
            foreach ($this->boothRows($c['assembly_no']) as $b) $electors[$b->booth_no] = (int) $b->total;
            foreach ($c['booths'] as $b) {
                $mine = 0; $rival = 0; $rp = '';
                foreach ($b['votes'] as $cid => $v) {
                    $p = $partyOf[$cid] ?? '';
                    if ($p === $focus) $mine += $v; elseif ($v > $rival) { $rival = $v; $rp = $p; }
                }
                $margin = $mine - $rival;
                $mp = $b['total'] ? round(1000 * $margin / $b['total']) / 10 : 0.0;
                $level = abs($mp) < self::SWING_PCT ? 'SWING' : ($mp < 0 ? 'WEAK' : 'STRONG');
                $el = $electors[$b['booth_no']] ?? 0;
                $rows[] = [$c['assembly_no'], $b['booth_no'], $b['booth_name'], $level, $focus, $mine, $rp, $rival, $margin, (float) $mp, self::pct($b['total'], $el), $el];
            }
        }
        $order = ['SWING' => 0, 'WEAK' => 1, 'STRONG' => 2];
        usort($rows, fn ($x, $y) => [$order[$x[3]], abs($x[9])] <=> [$order[$y[3]], abs($y[9])]);
        foreach ($rows as $x) { $x[3] = t('report.priority.' . $x[3]); $r->add($x); }
        return $r;
    }

    // ---------------------------------------------------------------- duplicates
    /** Same voter name + father/husband name within an assembly (or a village/ward), ignoring case, spaces and punctuation. */
    public function duplicates(?int $a, bool $byWard): Report
    {
        $r = new Report(t('report.duplicate-voters'), $this->h('report.group', 'col.assemblyNo', 'col.villageWard', 'col.boothNo', 'col.serialNo',
            'col.epicNo', 'col.name', 'col.relativeName', 'col.gender', 'col.age', 'col.houseNo', 'col.address', 'col.mobile'));
        $groups = [];
        foreach ($this->voters->searchAll($this->filter($a, null)) as $v) {
            $n = Text::nameKey($v->name);
            if ($n === '') continue;
            $k = $v->assembly_no . '|' . ($byWard ? Text::nameKey($v->village_ward) . '|' : '') . $n . '|' . Text::nameKey($v->relative_name);
            $groups[$k][] = $v;
        }
        $g = 0; $people = 0;
        foreach ($groups as $list) {
            if (count($list) < 2) continue;
            $g++; $people += count($list);
            foreach ($list as $v) $r->add([$g, $v->assembly_no, $v->village_ward, $v->booth_no, $v->serial_no, $v->epic_no, $v->name, $v->relative_name,
                $v->genderLabel(), $v->age, $v->house_no, $v->fullAddress(), $v->mobile]);
        }
        $r->subtitle($this->scope($a, null) . ' | ' . t($byWard ? 'report.dup.byWard' : 'report.dup.byAssembly') . ' | ' . t('report.dup.found', $g, $people));
        return $r;
    }
}
