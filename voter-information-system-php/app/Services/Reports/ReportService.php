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
        'voters' => ['assembly-summary', 'booth-summary', 'village-summary', 'age-gender', 'category-summary', 'caste-summary'],
        'outreach' => ['first-time-voters', 'senior-voters', 'households', 'large-households', 'phone-bank', 'birthdays'],
        'election' => ['results-summary', 'party-performance', 'booth-priority'],
        'quality' => ['duplicate-voters'],
        'analytics' => AnalyticsService::IDS,
    ];
    /** Sensitive personal data (social category): needs REPORT_SENSITIVE. */
    public const ADMIN_ONLY = ['category-summary', 'caste-summary', 'caste-booth', 'team-activity'];
    public const SWING_PCT = 10.0;

    /** Ward No filter for the report being built (null = all wards). */
    public ?int $ward = null;

    public function __construct(private ResultService $results, private VoterService $voters) {}

    public static function all(): array { return array_merge(...array_values(self::SECTIONS)); }

    public function build(string $id, ?int $assemblyNo, ?int $boothNo, ?Election $e, ?string $scope = null, ?string $party = null): Report
    {
        if (in_array($id, AnalyticsService::IDS, true)) {
            $an = app(AnalyticsService::class); $an->ward = $this->ward;
            return $an->build($id, $assemblyNo, $boothNo, $e, $party);
        }
        return match ($id) {
            'assembly-summary' => $this->assemblySummary($assemblyNo),
            'booth-summary' => $this->boothSummary($assemblyNo),
            'village-summary' => $this->villageSummary($assemblyNo),
            'age-gender' => $this->ageGender($assemblyNo, $boothNo),
            'category-summary' => $this->categorySummary($assemblyNo),
            'caste-summary' => $this->casteSummary($assemblyNo, $boothNo),
            'birthdays' => $this->birthdays($assemblyNo, $boothNo),
            'first-time-voters' => $this->voterList($id, $assemblyNo, $boothNo, 18, 21, false),
            'senior-voters' => $this->voterList($id, $assemblyNo, $boothNo, 85, null, false),
            'phone-bank' => $this->voterList($id, $assemblyNo, $boothNo, null, null, true),
            'large-households' => $this->largeHouseholds($assemblyNo),
            'households' => $this->largeHouseholds($assemblyNo, 1, 'households'),
            'results-summary' => $this->resultsSummary($e, $assemblyNo),
            'party-performance' => $this->partyPerformance($e, $assemblyNo),
            'booth-priority' => $this->boothPriority($e, $assemblyNo, $party),
            'duplicate-voters' => $this->duplicates($assemblyNo, $scope === 'ward'),
        };
    }

    private function h(string ...$keys): array { return array_map(fn ($k) => t($k), $keys); }
    private static function pct($part, $whole): float { return $whole ? round(10000 * $part / $whole) / 100 : 0.0; }
    private function assemblyName(int $no, ?string $fallback = ''): string { return Assembly::where('assembly_no', $no)->value('name') ?? (string) $fallback; }

    public function scope(?int $a, ?int $b): string
    {
        $s = [];
        if ($a !== null) $s[] = t('col.assemblyNo') . " $a " . $this->assemblyName($a);
        if ($this->ward !== null) $s[] = t('col.wardNo') . " {$this->ward}";
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
        $q = \App\Support\Area::voters()->selectRaw('assembly_no, max(assembly_name) as aname, count(distinct booth_no) as booths, ' . self::COUNTS)
            ->groupBy('assembly_no')->orderBy('assembly_no');
        if ($only !== null) $q->where('assembly_no', $only);
        if ($this->ward !== null) $q->where('ward_no', $this->ward);
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
        return \App\Support\Area::voters()->selectRaw('assembly_no, booth_no, max(booth_name) as bname, ' . self::COUNTS)
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))->groupBy('assembly_no', 'booth_no')->orderBy('assembly_no')->orderBy('booth_no')->get();
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
        $rows = \App\Support\Area::voters()->selectRaw("coalesce(village_ward, '-') as vw, " . self::COUNTS)
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))->groupBy('village_ward')->orderByDesc('total')->get();
        foreach ($rows as $x) $r->add([$x->vw, (int) $x->total, (int) $x->male, (int) $x->female, (int) $x->other, (int) $x->with_mobile,
            self::pct($x->with_mobile, $x->total), (int) $x->young, (int) $x->senior]);
        return $r;
    }

    public function ageGender(?int $a, ?int $b): Report
    {
        $groups = [[18, 25], [26, 35], [36, 45], [46, 60], [61, 80], [81, 200]];
        $counts = array_fill(0, count($groups) + 1, ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0]);
        $rows = \App\Support\Area::voters()->selectRaw('age, gender, count(*) as c')->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))
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
            // each count opens the voter list with that age group (and gender), ready for Excel / PDF / print
            $links = [];
            if ($i < count($groups)) {
                $base = ['assemblyNo' => $a, 'wardNo' => $this->ward, 'boothNo' => $b, 'ageMin' => $groups[$i][0], 'ageMax' => $groups[$i][1] >= 200 ? null : $groups[$i][1]];
                $links = [0 => $base, 1 => $base + ['gender' => 'MALE'], 2 => $base + ['gender' => 'FEMALE'], 3 => $base + ['gender' => 'OTHER'], 4 => $base];
            }
            $r->add([$label, $c['MALE'], $c['FEMALE'], $c['OTHER'], $t, self::pct($t, $grand)], $links);
            foreach ($c as $g => $n) $tot[$g] += $n;
        }
        $r->totals = [t('report.total'), $tot['MALE'], $tot['FEMALE'], $tot['OTHER'], $grand, $grand ? 100.0 : 0.0];
        $all = ['assemblyNo' => $a, 'wardNo' => $this->ward, 'boothNo' => $b];
        $r->links['totals'] = [1 => $all + ['gender' => 'MALE'], 2 => $all + ['gender' => 'FEMALE'], 3 => $all + ['gender' => 'OTHER'], 4 => $all];
        return $r;
    }

    /** Voters per caste (as recorded), with gender split and mobile reach. */
    public function casteSummary(?int $a, ?int $b): Report
    {
        $r = (new Report(t('report.caste-summary'), $this->h('col.caste', 'gender.MALE', 'gender.FEMALE', 'gender.OTHER', 'report.total',
            'report.sharePct', 'report.withMobile', 'report.mobilePct')))->subtitle($this->scope($a, $b) . ' | ' . t('report.sensitiveNote'));
        $rows = \App\Support\Area::voters()->selectRaw("trim(coalesce(caste, '')) as caste, gender, count(*) as c,
                sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as m")
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))->when($b !== null, fn ($q) => $q->where('booth_no', $b))
            ->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))
            ->groupBy(DB::raw("trim(coalesce(caste, ''))"), 'gender')->get();
        $by = [];
        foreach ($rows as $x) {
            $k = mb_strtolower($x->caste) ?: '';
            $by[$k] ??= ['name' => $x->caste !== '' ? $x->caste : t('report.cat.none'), 'g' => ['MALE' => 0, 'FEMALE' => 0, 'OTHER' => 0], 'm' => 0];
            $by[$k]['g'][in_array($x->gender, ['MALE', 'FEMALE'], true) ? $x->gender : 'OTHER'] += (int) $x->c;
            $by[$k]['m'] += (int) $x->m;
        }
        $grand = array_sum(array_map(fn ($x) => array_sum($x['g']), $by));
        uasort($by, fn ($p, $q) => array_sum($q['g']) <=> array_sum($p['g']));
        $tot = [0, 0, 0, 0, 0];
        foreach ($by as $k => $x) {
            $t = array_sum($x['g']);
            $r->add([$x['name'], $x['g']['MALE'], $x['g']['FEMALE'], $x['g']['OTHER'], $t, self::pct($t, $grand), $x['m'], self::pct($x['m'], $t)]);
            $tot = [$tot[0] + $x['g']['MALE'], $tot[1] + $x['g']['FEMALE'], $tot[2] + $x['g']['OTHER'], $tot[3] + $t, $tot[4] + $x['m']];
        }
        $r->totals = [t('report.total'), $tot[0], $tot[1], $tot[2], $tot[3], $grand ? 100.0 : 0.0, $tot[4], self::pct($tot[4], $tot[3])];
        return $r;
    }

    /** Birthdays and marriage anniversaries in the next 30 days (greetings, visits), soonest first. */
    public function birthdays(?int $a, ?int $b): Report
    {
        $r = new Report(t('report.birthdays'), $this->h('report.daysLeft', 'report.occasion', 'col.assemblyNo', 'col.boothNo', 'col.serialNo',
            'col.epicNo', 'col.name', 'col.nameLocal', 'col.relativeName', 'col.houseNo', 'col.address', 'col.mobile'));
        $today = new \DateTimeImmutable('today');
        $found = [];
        $q = $this->filter($a, $b)->apply(Voter::query())->where(fn ($w) => $w->whereNotNull('dob')->orWhereNotNull('anniversary'))
            ->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'))->orderBy('id');
        foreach ($q->lazy(2000) as $v) {
            foreach (['dob' => 'birthday', 'anniversary' => 'anniversary'] as $col => $kind) {
                if (!$v->$col) continue;
                $date = \DateTimeImmutable::createFromInterface($v->$col);
                [$m, $d] = [(int) $date->format('m'), (int) $date->format('d')];
                $y = (int) $today->format('Y');
                $next = $today->setDate($y, $m, $m === 2 && $d === 29 && !checkdate(2, 29, $y) ? 28 : $d);
                if ($next < $today) $next = $today->setDate($y + 1, $m, $m === 2 && $d === 29 && !checkdate(2, 29, $y + 1) ? 28 : $d);
                $days = (int) $today->diff($next)->days;
                if ($days > 30) continue;
                $years = (int) $next->format('Y') - (int) $date->format('Y');
                $found[] = [$days, t('report.occasion.' . $kind) . ' ' . $date->format('d-m') . ' (' . $years . ')', $v];
            }
            if (count($found) >= Report::MAX_ROWS) { $r->capped = true; break; }
        }
        usort($found, fn ($x, $y) => [$x[0], $x[2]->booth_no, $x[2]->serial_no] <=> [$y[0], $y[2]->booth_no, $y[2]->serial_no]);
        foreach ($found as [$days, $label, $v]) {
            $r->add([$days, $label, $v->assembly_no, $v->booth_no, $v->serial_no, $v->epic_no, $v->name, $v->name_local, $v->relative_name,
                $v->house_no, $v->fullAddress(), $v->mobile]);
        }
        $r->subtitle($this->scope($a, $b) . ' | ' . t('report.count', count($r->rows)));
        return $r;
    }

    public function categorySummary(?int $a): Report
    {
        $r = (new Report(t('report.category-summary'), $this->h('col.assemblyNo', 'col.boothNo', 'col.boothName', 'report.cat.GEN', 'report.cat.OBC',
            'report.cat.SC', 'report.cat.ST', 'report.cat.other', 'report.cat.none', 'report.total')))
            ->subtitle($this->scope($a, null) . ' | ' . t('report.sensitiveNote'));
        $rows = \App\Support\Area::voters()->selectRaw("assembly_no, booth_no, upper(trim(coalesce(category, ''))) as cat, count(*) as c")
            ->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))->groupBy('assembly_no', 'booth_no', DB::raw("upper(trim(coalesce(category, '')))"))
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
        $f->set('wardNo', $this->ward);
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
        foreach ($this->voters->searchLazy($f) as $v) {
            if ($phoneBank && isset($blocked[$v->mobile])) { $skipped++; continue; }
            if (count($r->rows) >= Report::MAX_ROWS) { $r->capped = true; break; }
            $r->add([$v->assembly_no, $v->booth_no, $v->serial_no, $v->epic_no, $v->name, $v->name_local, $v->relative_name, $v->genderLabel(),
                $v->age, $v->house_no, $v->fullAddress(), $v->mobile]);
        }
        $r->subtitle .= ' | ' . t('report.count', count($r->rows)) . ($phoneBank && $skipped ? ' | ' . t('report.optOutsExcluded', $skipped) : '');
        return $r;
    }

    /** Houses with at least $min voters (1 = every household). House numbers match ignoring spaces / hyphens / dots. */
    public function largeHouseholds(?int $a, int $min = 5, string $id = 'large-households'): Report
    {
        $r = (new Report(t('report.' . $id), $this->h('col.assemblyNo', 'col.boothNo', 'col.houseNo', 'col.address', 'col.villageWard',
            'report.votersInHouse', 'report.withMobile', 'report.members')))->subtitle($this->scope($a, null));
        // The database groups the houses (one summary row each): a big assembly (1 lakh+ voters) never has to fit in PHP memory.
        $mysql = DB::connection()->getDriverName() === 'mysql';
        if ($mysql) DB::statement('SET SESSION group_concat_max_len = 65535');
        $member = $mysql ? "group_concat(concat(name, case when age is null then '' else concat(' (', age, ')') end) order by booth_no, serial_no separator ', ')"
                         : "group_concat(name || case when age is null then '' else ' (' || age || ')' end, ', ')";
        $q = $this->filter($a, null)->apply(Voter::query())->whereNotNull('house_no')->where('house_no', '<>', '')->toBase()
            ->selectRaw("assembly_no, min(booth_no) as b, min(serial_no) as sr, max(house_no) as house, max(address) as address, max(village_ward) as ward,
                count(*) as n, sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as m, $member as members")
            ->groupBy('assembly_no', DB::raw(Text::HOUSE_KEY_SQL), DB::raw("lower(trim(coalesce(address, '')))"), DB::raw("lower(trim(coalesce(village_ward, '')))"))
            ->when($min > 1, fn ($x) => $x->havingRaw('count(*) >= ?', [$min]))
            ->when($min > 1, fn ($x) => $x->orderByDesc('n'))->orderBy('b')->orderBy('sr')->limit(Report::MAX_ROWS + 1);
        foreach ($q->cursor() as $h) {
            if (count($r->rows) >= Report::MAX_ROWS) { $r->capped = true; break; }
            // House No opens the voter list of that house
            $link = ['assemblyNo' => (int) $h->assembly_no, 'houseNo' => $h->house, 'villageWard' => $h->ward];
            $r->add([(int) $h->assembly_no, (int) $h->b, $h->house, $h->address, $h->ward, (int) $h->n, (int) $h->m, (string) $h->members], [2 => $link, 5 => $link]);
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
            $inWard = $this->ward !== null ? ($this->voters->boothsByWard($c['assembly_no'])[$this->ward] ?? []) : null;
            foreach ($c['booths'] as $b) {
                if ($inWard !== null && !in_array($b['booth_no'], $inWard)) continue;
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
        // pass 1: only the matching key per voter (a whole assembly fits in memory)
        $groups = [];
        $q = $this->filter($a, null)->apply(Voter::query())->select('id', 'assembly_no', 'village_ward', 'name', 'relative_name')
            ->orderBy('assembly_no')->orderBy('booth_no')->orderBy('serial_no')->orderBy('id')->toBase();
        foreach ($q->lazy(5000) as $v) {
            $n = Text::nameKey($v->name);
            if ($n === '') continue;
            $groups[$v->assembly_no . '|' . ($byWard ? Text::nameKey($v->village_ward) . '|' : '') . $n . '|' . Text::nameKey($v->relative_name)][] = $v->id;
        }
        $groups = array_filter($groups, fn ($ids) => count($ids) > 1);
        // pass 2: full details only for the duplicates, 1,000 voters at a time, up to the report row limit
        $g = 0; $people = 0; $batch = [];
        $flush = function () use (&$batch, $r) {
            if (!$batch) return;
            $models = Voter::whereIn('id', array_column($batch, 1))->get()->keyBy('id');
            foreach ($batch as [$group, $id]) if ($v = $models[$id] ?? null) $r->add([$group, $v->assembly_no, $v->village_ward, $v->booth_no, $v->serial_no,
                $v->epic_no, $v->name, $v->relative_name, $v->genderLabel(), $v->age, $v->house_no, $v->fullAddress(), $v->mobile]);
            $batch = [];
        };
        foreach ($groups as $ids) {
            $g++; $people += count($ids);
            if (count($r->rows) + count($batch) >= Report::MAX_ROWS) { $r->capped = true; continue; }
            foreach ($ids as $id) $batch[] = [$g, $id];
            if (count($batch) >= 1000) $flush();
        }
        $flush();
        unset($groups);
        $r->subtitle($this->scope($a, null) . ' | ' . t($byWard ? 'report.dup.byWard' : 'report.dup.byAssembly') . ' | ' . t('report.dup.found', $g, $people));
        return $r;
    }
}
