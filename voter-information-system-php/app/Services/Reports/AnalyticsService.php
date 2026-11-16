<?php

namespace App\Services\Reports;

use App\Models\Booth;
use App\Models\Election;
use App\Services\Results\ResultService;
use App\Services\Voters\VoterService;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/**
 * Campaign analytics built from the data already in the system: voters and households, the field survey
 * (political status, caste, beneficiaries, membership, origin, mobile, DOB), election results, messaging and the audit log.
 * Every analysis respects assembly / ward / booth and leaves out voters marked as deleted from the roll.
 */
class AnalyticsService
{
    public const IDS = ['booth-scorecard', 'voter-sentiment', 'survey-coverage', 'caste-booth', 'beneficiaries', 'worker-coverage',
        'household-insights', 'origin', 'turnout-profile', 'campaign-reach', 'grievances', 'team-activity'];
    public const SENSITIVE = ['caste-booth', 'team-activity'];
    /** Booth grade thresholds (support index = support % - opposition % of surveyed voters). */
    public const STRONG = 20, WEAK = -20, MIN_SURVEYED_PCT = 10, VOTERS_PER_WORKER = 100;

    public ?int $ward = null;

    public function __construct(private ReportService $reports, private ResultService $results, private VoterService $voters) {}

    public function build(string $id, ?int $a, ?int $b, ?Election $e, ?string $party): Report
    {
        $this->reports->ward = $this->ward;
        $r = match ($id) {
            'booth-scorecard' => $this->boothScorecard($a, $e, $party),
            'voter-sentiment' => $this->sentiment($a, $b),
            'survey-coverage' => $this->coverage($a),
            'caste-booth' => $this->casteBooth($a),
            'beneficiaries' => $this->beneficiaries($a, $b),
            'worker-coverage' => $this->workers($a),
            'household-insights' => $this->households($a),
            'origin' => $this->origin($a, $b),
            'turnout-profile' => $this->turnout($a, $e, $party),
            'campaign-reach' => $this->campaigns($a),
            'grievances' => $this->grievances($a),
            'team-activity' => $this->team(),
        };
        if ($r->note === '' && t('an.note.' . $id) !== 'an.note.' . $id) $r->note = t('an.note.' . $id);
        return $r;
    }

    // ------------------------------------------------------------------ helpers
    /** Voters in scope (assembly, ward, booth), without voters deleted from the roll. */
    public function base(?int $a, ?int $b = null, bool $withDeleted = false)
    {
        return DB::table('voters')->when($a !== null, fn ($q) => $q->where('assembly_no', $a))
            ->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))->when($b !== null, fn ($q) => $q->where('booth_no', $b))
            ->when(!$withDeleted, fn ($q) => $q->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted')));
    }

    /** Political status grouped into S(upport) / N(eutral) / O(pposition) / X (other answer) / U (not surveyed). */
    public const SENTIMENT_SQL = "case
        when political_status is null or trim(political_status) = '' then 'U'
        when lower(political_status) like '%oppos%' or lower(political_status) like '%against%' or lower(political_status) like '%anti%' then 'O'
        when lower(political_status) like '%neutral%' or lower(political_status) like '%undecided%' or lower(political_status) like '%swing%' then 'N'
        when lower(political_status) like '%support%' or lower(political_status) like '%karyakarta%' or lower(political_status) like '%worker%'
          or lower(political_status) like '%member%' or lower(political_status) like '%favour%' or lower(political_status) like '%favor%'
          or lower(political_status) like '%loyal%' then 'S'
        else 'X' end";
    /** The same grouping as SENTIMENT_SQL, in PHP. */
    public static function sentimentOf(?string $status): string
    {
        $s = mb_strtolower(trim((string) $status));
        if ($s === '') return 'U';
        foreach (['O' => ['oppos', 'against', 'anti'], 'N' => ['neutral', 'undecided', 'swing'],
                     'S' => ['support', 'karyakarta', 'worker', 'member', 'favour', 'favor', 'loyal']] as $k => $words) {
            foreach ($words as $w) if (str_contains($s, $w)) return $k;
        }
        return 'X';
    }

    private const WORKER_SQL = "case when (membership_no is not null and membership_no <> '')
        or lower(coalesce(political_status, '')) like '%karyakarta%' or lower(coalesce(political_status, '')) like '%worker%'
        or (organisational_status is not null and organisational_status <> '') then 1 else 0 end";

    private static function pct($part, $whole): float { return $whole ? round(1000 * $part / $whole) / 10 : 0.0; }
    private function h(string ...$k): array { return array_map(fn ($x) => t($x), $k); }
    private function scope(?int $a, ?int $b = null): string { return $this->reports->scope($a, $b); }
    private function link(?int $a, array $extra): array { return array_filter(['assemblyNo' => $a, 'wardNo' => $this->ward] + $extra, fn ($v) => $v !== null); }
    private function boothNames(?int $a): array { return Booth::when($a !== null, fn ($q) => $q->where('assembly_no', $a))->get()->mapWithKeys(fn ($b) => [$b->assembly_no . '/' . $b->booth_no => $b->name])->all(); }

    /** Sentiment counts per booth: [booth key => ['a' => assembly, 'b' => booth, 'S' => n, 'N' => n, 'O' => n, 'X' => n, 'U' => n, 'total' => n]]. */
    public function sentimentByBooth(?int $a, ?int $b = null): array
    {
        $out = [];
        foreach ($this->base($a, $b)->selectRaw('assembly_no, booth_no, political_status, count(*) as c')
                     ->groupBy('assembly_no', 'booth_no', 'political_status')->orderBy('assembly_no')->orderBy('booth_no')->get() as $x) {
            $x->s = self::sentimentOf($x->political_status);
            $k = $x->assembly_no . '/' . $x->booth_no;
            $out[$k] ??= ['a' => (int) $x->assembly_no, 'b' => (int) $x->booth_no, 'S' => 0, 'N' => 0, 'O' => 0, 'X' => 0, 'U' => 0, 'total' => 0];
            $out[$k][$x->s] += (int) $x->c; $out[$k]['total'] += (int) $x->c;
        }
        return $out;
    }

    /** Focus-party margin % per booth in the given election (focus = chosen party or the seat winner). */
    private function margins(?Election $e, ?int $a, ?string $party): array
    {
        if (!$e) return [];
        $out = [];
        foreach ($this->results->electionContests($e, $a) as $c) {
            if (!$c['winner']) continue;
            $focus = $party ?: $c['winner']['candidate']->partyShort();
            $partyOf = [];
            foreach ($c['totals'] as $t) $partyOf[$t['candidate']->id] = $t['candidate']->partyShort();
            foreach ($c['booths'] as $bt) {
                $mine = 0; $rival = 0;
                foreach ($bt['votes'] as $cid => $v) { if (($partyOf[$cid] ?? '') === $focus) $mine += $v; else $rival = max($rival, $v); }
                $out[$c['assembly_no'] . '/' . $bt['booth_no']] = ['focus' => $focus, 'margin' => $bt['total'] ? round(1000 * ($mine - $rival) / $bt['total']) / 10 : 0.0,
                    'share' => $bt['total'] ? round(1000 * $mine / $bt['total']) / 10 : 0.0, 'votes' => $bt['total']];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ 1. booth scorecard
    public function boothScorecard(?int $a, ?Election $e, ?string $party): Report
    {
        $r = (new Report(t('report.booth-scorecard'), $this->h('col.assemblyNo', 'col.boothNo', 'col.boothName', 'dash.voters', 'an.support', 'an.neutral',
            'an.opposition', 'an.unknown', 'an.supportIndex', 'result.margin', 'report.mobilePct', 'an.workers', 'an.votersPerWorker', 'an.grade', 'an.action')))
            ->subtitle($this->scope($a) . ($e ? ' | ' . $e->name : ''));
        $sent = $this->sentimentByBooth($a);
        $extra = [];
        foreach ($this->base($a)->selectRaw("assembly_no, booth_no, sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as m, sum(" . self::WORKER_SQL . ") as w")
                     ->groupBy('assembly_no', 'booth_no')->get() as $x) $extra[$x->assembly_no . '/' . $x->booth_no] = $x;
        $margins = $this->margins($e, $a, $party);
        $names = $this->boothNames($a);
        $grades = ['A' => 0, 'B' => 0, 'C' => 0, '-' => 0]; $chart = ['labels' => [], 'S' => [], 'N' => [], 'O' => [], 'U' => []];
        foreach ($sent as $k => $s) {
            $surveyed = $s['total'] - $s['U'];
            $index = $surveyed ? round(1000 * ($s['S'] - $s['O']) / $surveyed) / 10 : null;
            $mg = $margins[$k]['margin'] ?? null;
            $lowData = self::pct($surveyed, $s['total']) < self::MIN_SURVEYED_PCT;
            $basis = !$lowData ? $index : $mg;
            $grade = $basis === null ? '-' : ($basis >= self::STRONG ? 'A' : ($basis <= self::WEAK ? 'C' : 'B'));
            $grades[$grade]++;
            $w = (int) ($extra[$k]->w ?? 0);
            $r->add([$s['a'], $s['b'], $names[$k] ?? '', $s['total'], $s['S'], $s['N'], $s['O'], $s['U'], $index, $mg, self::pct((int) ($extra[$k]->m ?? 0), $s['total']),
                $w, $w ? (int) round($s['total'] / $w) : null, $grade, t('an.action.' . $grade)],
                [3 => ['assemblyNo' => $s['a'], 'wardNo' => $this->ward, 'boothNo' => $s['b']]]);
            $chart['labels'][] = (string) $s['b']; foreach (['S', 'N', 'O', 'U'] as $c) $chart[$c][] = $s[$c];
        }
        usort($r->rows, fn ($x, $y) => [$x[13] === '-' ? 9 : ord($x[13]), $x[1]] <=> [$y[13] === '-' ? 9 : ord($y[13]), $y[1]]);
        $r->links = [];   // rows re-ordered: rebuild links
        foreach ($r->rows as $i => $row) {
            $l = fn ($st) => ['assemblyNo' => $row[0], 'wardNo' => $this->ward, 'boothNo' => $row[1], 'sentiment' => $st];
            $r->links[$i] = [3 => $l(null), 4 => $l('S'), 5 => $l('N'), 6 => $l('O'), 7 => $l('U')];
        }
        $r->subtitle .= ' | A: ' . $grades['A'] . ', B: ' . $grades['B'] . ', C: ' . $grades['C'] . ($grades['-'] ? ', -: ' . $grades['-'] : '');
        $r->note = t('an.note.booth-scorecard', self::STRONG, self::WEAK, self::MIN_SURVEYED_PCT);
        $r->chart = ['type' => 'bar', 'stacked' => true, 'labels' => $chart['labels'], 'series' => [
            [t('an.support'), $chart['S'], 'support'], [t('an.neutral'), $chart['N'], 'neutral'], [t('an.opposition'), $chart['O'], 'opposition'], [t('an.unknown'), $chart['U'], 'unknown']]];
        return $r;
    }

    // ------------------------------------------------------------------ 2. voter sentiment
    public function sentiment(?int $a, ?int $b): Report
    {
        $r = (new Report(t('report.voter-sentiment'), $this->h('col.assemblyNo', 'col.boothNo', 'dash.voters', 'an.support', 'an.neutral', 'an.opposition', 'an.other',
            'an.unknown', 'an.surveyedPct', 'an.supportPct')))->subtitle($this->scope($a, $b));
        $tot = ['S' => 0, 'N' => 0, 'O' => 0, 'X' => 0, 'U' => 0, 'total' => 0];
        foreach ($this->sentimentByBooth($a, $b) as $s) {
            $surveyed = $s['total'] - $s['U'];
            $lk = fn ($st) => ['assemblyNo' => $s['a'], 'wardNo' => $this->ward, 'boothNo' => $s['b'], 'sentiment' => $st];
            $r->add([$s['a'], $s['b'], $s['total'], $s['S'], $s['N'], $s['O'], $s['X'], $s['U'], self::pct($surveyed, $s['total']), self::pct($s['S'], $surveyed)],
                [2 => $lk(null), 3 => $lk('S'), 4 => $lk('N'), 5 => $lk('O'), 6 => $lk('X'), 7 => $lk('U')]);
            foreach ($tot as $k => $v) $tot[$k] += $s[$k];
        }
        $sv = $tot['total'] - $tot['U'];
        $r->totals = [t('report.total'), '', $tot['total'], $tot['S'], $tot['N'], $tot['O'], $tot['X'], $tot['U'], self::pct($sv, $tot['total']), self::pct($tot['S'], $sv)];
        $tl = fn ($st) => $this->link($a, ['boothNo' => $b, 'sentiment' => $st]);
        $r->links['totals'] = [2 => $tl(null), 3 => $tl('S'), 4 => $tl('N'), 5 => $tl('O'), 6 => $tl('X'), 7 => $tl('U')];
        $r->chart = ['type' => 'doughnut', 'labels' => $this->h('an.support', 'an.neutral', 'an.opposition', 'an.other', 'an.unknown'),
            'series' => [[t('dash.voters'), [$tot['S'], $tot['N'], $tot['O'], $tot['X'], $tot['U']]]], 'roles' => ['support', 'neutral', 'opposition', 'other', 'unknown']];
        return $r;
    }

    // ------------------------------------------------------------------ 3. survey coverage
    public function coverage(?int $a): Report
    {
        $r = (new Report(t('report.survey-coverage'), $this->h('col.assemblyNo', 'col.boothNo', 'dash.voters', 'report.mobilePct', 'an.withStatus', 'an.withCaste',
            'an.withDob', 'an.withBen', 'an.coverage', 'an.deleted')))->subtitle($this->scope($a));
        $q = $this->base($a, null, true)->selectRaw("assembly_no, booth_no,
            sum(case when deleted = 1 then 0 else 1 end) as total,
            sum(case when deleted = 1 then 1 else 0 end) as del,
            sum(case when deleted = 1 or mobile is null or mobile = '' then 0 else 1 end) as m,
            sum(case when deleted = 1 or political_status is null or political_status = '' then 0 else 1 end) as st,
            sum(case when deleted = 1 or caste is null or caste = '' then 0 else 1 end) as ca,
            sum(case when deleted = 1 or dob is null then 0 else 1 end) as dob,
            sum(case when deleted = 1 or ((beneficiary1 is null or beneficiary1 = '') and (beneficiary2 is null or beneficiary2 = '') and (beneficiary3 is null or beneficiary3 = '')) then 0 else 1 end) as ben")
            ->groupBy('assembly_no', 'booth_no')->orderBy('assembly_no')->orderBy('booth_no')->get();
        $t = array_fill_keys(['total', 'del', 'm', 'st', 'ca', 'dob', 'ben'], 0); $ch = ['l' => [], 'm' => [], 'st' => [], 'ca' => []];
        foreach ($q as $x) {
            $n = (int) $x->total; $p = fn ($f) => self::pct((int) $x->$f, $n);
            $cov = round(($p('m') + $p('st') + $p('ca')) / 3, 1);
            $r->add([(int) $x->assembly_no, (int) $x->booth_no, $n, $p('m'), $p('st'), $p('ca'), $p('dob'), $p('ben'), $cov, (int) $x->del],
                [2 => ['assemblyNo' => (int) $x->assembly_no, 'wardNo' => $this->ward, 'boothNo' => (int) $x->booth_no], 9 => ['assemblyNo' => (int) $x->assembly_no, 'wardNo' => $this->ward, 'boothNo' => (int) $x->booth_no, 'deleted' => 'only']]);
            foreach ($t as $k => $v) $t[$k] += (int) $x->$k;
            $ch['l'][] = (string) $x->booth_no; $ch['m'][] = $p('m'); $ch['st'][] = $p('st'); $ch['ca'][] = $p('ca');
        }
        $P = fn ($f) => self::pct($t[$f], $t['total']);
        $r->totals = [t('report.total'), '', $t['total'], $P('m'), $P('st'), $P('ca'), $P('dob'), $P('ben'), round(($P('m') + $P('st') + $P('ca')) / 3, 1), $t['del']];
        $r->chart = ['type' => 'bar', 'labels' => $ch['l'], 'max' => 100, 'suffix' => '%', 'series' => [[t('report.mobilePct'), $ch['m']], [t('an.withStatus'), $ch['st']], [t('an.withCaste'), $ch['ca']]]];
        return $r;
    }

    // ------------------------------------------------------------------ 4. caste x booth
    public function casteBooth(?int $a): Report
    {
        $rows = $this->base($a)->selectRaw("assembly_no, booth_no, trim(coalesce(caste, '')) as caste, count(*) as c")
            ->groupBy('assembly_no', 'booth_no', DB::raw("trim(coalesce(caste, ''))"))->get();
        $totals = [];
        foreach ($rows as $x) if ($x->caste !== '') $totals[$x->caste] = ($totals[$x->caste] ?? 0) + (int) $x->c;
        arsort($totals); $top = array_slice(array_keys($totals), 0, 8);
        $r = (new Report(t('report.caste-booth'), array_merge($this->h('col.assemblyNo', 'col.boothNo'), $top, $this->h('an.other', 'report.cat.none', 'report.total'))))
            ->subtitle($this->scope($a) . ' | ' . t('report.sensitiveNote'));
        $by = [];
        foreach ($rows as $x) {
            $k = $x->assembly_no . '/' . $x->booth_no;
            $by[$k] ??= ['a' => (int) $x->assembly_no, 'b' => (int) $x->booth_no, 'v' => array_fill(0, count($top) + 2, 0)];
            $i = $x->caste === '' ? count($top) + 1 : (($j = array_search($x->caste, $top, true)) !== false ? $j : count($top));
            $by[$k]['v'][$i] += (int) $x->c;
        }
        ksort($by, SORT_NATURAL);
        $grand = array_fill(0, count($top) + 2, 0);
        foreach ($by as $x) {
            $links = [];
            foreach ($top as $j => $c) $links[2 + $j] = ['assemblyNo' => $x['a'], 'wardNo' => $this->ward, 'boothNo' => $x['b'], 'caste' => $c];
            $r->add(array_merge([$x['a'], $x['b']], $x['v'], [array_sum($x['v'])]), $links);
            foreach ($x['v'] as $i => $n) $grand[$i] += $n;
        }
        $r->totals = array_merge([t('report.total'), ''], $grand, [array_sum($grand)]);
        $r->chart = ['type' => 'bar', 'stacked' => true, 'labels' => array_map(fn ($x) => (string) $x['b'], array_values($by)),
            'series' => array_map(fn ($c, $j) => [$c, array_map(fn ($x) => $x['v'][$j], array_values($by))], $top, array_keys($top))];
        return $r;
    }

    // ------------------------------------------------------------------ 5. scheme beneficiaries
    public function beneficiaries(?int $a, ?int $b): Report
    {
        $r = (new Report(t('report.beneficiaries'), $this->h('an.scheme', 'an.beneficiaries', 'report.withMobile', 'report.mobilePct', 'an.support', 'an.neutral',
            'an.opposition', 'an.unknown', 'report.booths')))->subtitle($this->scope($a, $b));
        $s = [];
        $q = $this->base($a, $b)->selectRaw('booth_no, mobile, beneficiary1, beneficiary2, beneficiary3, ' . self::SENTIMENT_SQL . ' as st')
            ->where(fn ($w) => $w->whereNotNull('beneficiary1')->orWhereNotNull('beneficiary2')->orWhereNotNull('beneficiary3'))->orderBy('id');
        foreach ($q->lazy(5000) as $v) {
            foreach (array_unique(array_filter([$v->beneficiary1, $v->beneficiary2, $v->beneficiary3], fn ($x) => trim((string) $x) !== '')) as $name) {
                $name = trim($name); $k = mb_strtolower($name);
                $s[$k] ??= ['name' => $name, 'n' => 0, 'm' => 0, 'S' => 0, 'N' => 0, 'O' => 0, 'U' => 0, 'b' => []];
                $s[$k]['n']++; if ($v->mobile) $s[$k]['m']++;
                $s[$k][in_array($v->st, ['S', 'N', 'O'], true) ? $v->st : 'U']++; $s[$k]['b'][$v->booth_no] = true;
            }
        }
        uasort($s, fn ($x, $y) => $y['n'] <=> $x['n']);
        foreach ($s as $x) $r->add([$x['name'], $x['n'], $x['m'], self::pct($x['m'], $x['n']), $x['S'], $x['N'], $x['O'], $x['U'], count($x['b'])],
            [0 => $this->link($a, ['boothNo' => $b, 'beneficiary' => $x['name']]), 1 => $this->link($a, ['boothNo' => $b, 'beneficiary' => $x['name']])]);
        $top = array_slice(array_values($s), 0, 12);
        $r->chart = ['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'labels' => array_column($top, 'name'), 'series' => [
            [t('an.support'), array_column($top, 'S'), 'support'], [t('an.neutral'), array_column($top, 'N'), 'neutral'],
            [t('an.opposition'), array_column($top, 'O'), 'opposition'], [t('an.unknown'), array_column($top, 'U'), 'unknown']]];
        return $r;
    }

    // ------------------------------------------------------------------ 6. worker coverage
    public function workers(?int $a): Report
    {
        $r = (new Report(t('report.worker-coverage'), $this->h('col.assemblyNo', 'col.boothNo', 'col.boothName', 'dash.voters', 'an.households', 'an.members',
            'an.karyakartas', 'an.orgRoles', 'an.workers', 'an.votersPerWorker', 'an.housesPerWorker', 'an.status')))->subtitle($this->scope($a));
        $houses = $this->houseCounts($a);
        $names = $this->boothNames($a);
        $q = $this->base($a)->selectRaw("assembly_no, booth_no, count(*) as total,
            sum(case when membership_no is not null and membership_no <> '' then 1 else 0 end) as mem,
            sum(case when lower(coalesce(political_status, '')) like '%karyakarta%' or lower(coalesce(political_status, '')) like '%worker%' then 1 else 0 end) as kar,
            sum(case when organisational_status is not null and organisational_status <> '' then 1 else 0 end) as org,
            sum(" . self::WORKER_SQL . ") as w")->groupBy('assembly_no', 'booth_no')->orderBy('assembly_no')->orderBy('booth_no')->get();
        $short = 0; $ch = ['l' => [], 'w' => [], 'vpw' => []];
        foreach ($q as $x) {
            $k = $x->assembly_no . '/' . $x->booth_no; $w = (int) $x->w; $vpw = $w ? (int) round($x->total / $w) : null;
            $need = !$w || $vpw > self::VOTERS_PER_WORKER; if ($need) $short++;
            $h = $houses[$k] ?? 0;
            $r->add([(int) $x->assembly_no, (int) $x->booth_no, $names[$k] ?? '', (int) $x->total, $h, (int) $x->mem, (int) $x->kar, (int) $x->org, $w, $vpw,
                $w ? (int) round($h / $w) : null, t($need ? 'an.needsWorkers' : 'an.ok')]);
            $ch['l'][] = (string) $x->booth_no; $ch['w'][] = $w;
        }
        $r->subtitle .= ' | ' . t('an.boothsShort', $short, count($q));
        $r->note = t('an.note.worker-coverage', self::VOTERS_PER_WORKER);
        $r->chart = ['type' => 'bar', 'labels' => $ch['l'], 'series' => [[t('an.workers'), $ch['w']]]];
        return $r;
    }

    /** Households per booth (house no. matched ignoring spaces / hyphens / dots, within the same village / ward). */
    private function houseCounts(?int $a): array
    {
        $out = [];
        foreach ($this->base($a)->whereNotNull('house_no')->where('house_no', '<>', '')->selectRaw('assembly_no, booth_no')
                     ->groupBy('assembly_no', 'booth_no', DB::raw(Text::HOUSE_KEY_SQL), DB::raw("lower(trim(coalesce(address, '')))"), DB::raw("lower(trim(coalesce(village_ward, '')))"))->cursor() as $x) {
            $k = $x->assembly_no . '/' . $x->booth_no; $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }

    /** Households in scope, each counted once (same rule as the dashboard tile). */
    private function houseTotal(?int $a): int
    {
        $n = 0;
        foreach ($this->base($a)->whereNotNull('house_no')->where('house_no', '<>', '')->selectRaw('1 as x')
                     ->groupBy(DB::raw(Text::HOUSE_KEY_SQL), DB::raw("lower(trim(coalesce(address, '')))"), DB::raw("lower(trim(coalesce(village_ward, '')))"))->cursor() as $_) $n++;
        return $n;
    }

    // ------------------------------------------------------------------ 7. household insights
    public function households(?int $a): Report
    {
        $r = (new Report(t('report.household-insights'), $this->h('col.assemblyNo', 'col.boothNo', 'dash.voters', 'an.households', 'an.avgSize', 'an.single',
            'an.big', 'an.noMobile', 'an.supportHouses', 'an.oppositionHouses')))->subtitle($this->scope($a));
        $rows = $this->base($a)->whereNotNull('house_no')->where('house_no', '<>', '')
            ->selectRaw("assembly_no, booth_no, count(*) as c,
                sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as m, sum(case when " . self::SENTIMENT_SQL . " = 'S' then 1 else 0 end) as s,
                sum(case when " . self::SENTIMENT_SQL . " = 'O' then 1 else 0 end) as o")
            ->groupBy('assembly_no', 'booth_no', DB::raw(Text::HOUSE_KEY_SQL), DB::raw("lower(trim(coalesce(address, '')))"), DB::raw("lower(trim(coalesce(village_ward, '')))"))->get();
        $by = []; $sizes = array_fill(0, 6, 0);
        foreach ($rows as $x) {
            $k = $x->assembly_no . '/' . $x->booth_no;
            $by[$k] ??= ['a' => (int) $x->assembly_no, 'b' => (int) $x->booth_no, 'v' => 0, 'h' => 0, 'one' => 0, 'big' => 0, 'nom' => 0, 'sup' => 0, 'opp' => 0];
            $n = (int) $x->c; $by[$k]['v'] += $n; $by[$k]['h']++;
            if ($n === 1) $by[$k]['one']++; if ($n >= 5) $by[$k]['big']++; if (!(int) $x->m) $by[$k]['nom']++;
            if ((int) $x->s) $by[$k]['sup']++; if ((int) $x->o) $by[$k]['opp']++;
            $sizes[min($n, 6) - 1]++;
        }
        ksort($by, SORT_NATURAL);
        $t = ['v' => 0, 'h' => 0, 'one' => 0, 'big' => 0, 'nom' => 0, 'sup' => 0, 'opp' => 0];
        foreach ($by as $x) {
            $r->add([$x['a'], $x['b'], $x['v'], $x['h'], $x['h'] ? round($x['v'] / $x['h'], 1) : 0.0, $x['one'], $x['big'], $x['nom'], $x['sup'], $x['opp']],
                [2 => ['assemblyNo' => $x['a'], 'wardNo' => $this->ward, 'boothNo' => $x['b']]]);
            foreach ($t as $k => $v) $t[$k] += $x[$k];
        }
        $t['h'] = $this->houseTotal($a);   // a house whose voters are split over two booths is counted once here
        $r->totals = [t('report.total'), '', $t['v'], $t['h'], $t['h'] ? round($t['v'] / $t['h'], 1) : 0.0, $t['one'], $t['big'], $t['nom'], $t['sup'], $t['opp']];
        $r->chart = ['type' => 'bar', 'labels' => ['1', '2', '3', '4', '5', '6+'], 'series' => [[t('dash.houses'), $sizes]], 'axisX' => t('an.votersInHouse')];
        return $r;
    }

    // ------------------------------------------------------------------ 8. place of origin
    public function origin(?int $a, ?int $b): Report
    {
        $r = (new Report(t('report.origin'), $this->h('an.origin', 'dash.voters', 'report.sharePct', 'report.withMobile', 'an.support', 'report.booths', 'an.topBooth')))
            ->subtitle($this->scope($a, $b));
        $rows = $this->base($a, $b)->whereNotNull('place_of_origin')->where('place_of_origin', '<>', '')
            ->selectRaw("trim(place_of_origin) as o, booth_no, count(*) as c, sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as m,
                sum(case when " . self::SENTIMENT_SQL . " = 'S' then 1 else 0 end) as s")
            ->groupBy(DB::raw('trim(place_of_origin)'), 'booth_no')->get();
        $by = [];
        foreach ($rows as $x) {
            $k = mb_strtolower($x->o);
            $by[$k] ??= ['o' => $x->o, 'c' => 0, 'm' => 0, 's' => 0, 'b' => []];
            $by[$k]['c'] += (int) $x->c; $by[$k]['m'] += (int) $x->m; $by[$k]['s'] += (int) $x->s; $by[$k]['b'][$x->booth_no] = (int) $x->c;
        }
        uasort($by, fn ($x, $y) => $y['c'] <=> $x['c']);
        $all = (int) $this->base($a, $b)->count();
        foreach ($by as $x) { arsort($x['b']); $r->add([$x['o'], $x['c'], self::pct($x['c'], $all), $x['m'], $x['s'], count($x['b']), array_key_first($x['b'])]); }
        $top = array_slice(array_values($by), 0, 12);
        $r->chart = ['type' => 'bar', 'horizontal' => true, 'labels' => array_column($top, 'o'), 'series' => [[t('dash.voters'), array_column($top, 'c')]]];
        return $r;
    }

    // ------------------------------------------------------------------ 9. turnout vs voter profile
    public function turnout(?int $a, ?Election $e, ?string $party): Report
    {
        $r = (new Report(t('report.turnout-profile'), $this->h('col.assemblyNo', 'col.boothNo', 'dash.voters', 'result.totalVotes', 'result.turnoutPct', 'an.youngPct',
            'an.womenPct', 'an.seniorPct', 'an.focusShare', 'result.margin')))->subtitle($this->scope($a) . ($e ? ' | ' . $e->name : ''));
        if (!$e) return $r->subtitle(t('result.noElection'));
        $m = $this->margins($e, $a, $party);
        $focus = $m ? reset($m)['focus'] : '';
        $ch = ['l' => [], 't' => [], 'y' => [], 'w' => []];
        foreach ($this->base($a)->selectRaw("assembly_no, booth_no, count(*) as total, sum(case when age between 18 and 25 then 1 else 0 end) as y,
                    sum(case when gender = 'FEMALE' then 1 else 0 end) as f, sum(case when age >= 60 then 1 else 0 end) as sr")
                     ->groupBy('assembly_no', 'booth_no')->orderBy('assembly_no')->orderBy('booth_no')->get() as $x) {
            $k = $x->assembly_no . '/' . $x->booth_no; if (!isset($m[$k])) continue;
            $n = (int) $x->total; $to = self::pct($m[$k]['votes'], $n);
            $r->add([(int) $x->assembly_no, (int) $x->booth_no, $n, $m[$k]['votes'], $to, self::pct($x->y, $n), self::pct($x->f, $n), self::pct($x->sr, $n),
                $m[$k]['share'], $m[$k]['margin']]);
            $ch['l'][] = (string) $x->booth_no; $ch['t'][] = $to; $ch['y'][] = self::pct($x->y, $n); $ch['w'][] = self::pct($x->f, $n);
        }
        if ($focus) $r->subtitle .= ' | ' . t('dash.focusParty') . ': ' . $focus;
        $r->chart = ['type' => 'line', 'labels' => $ch['l'], 'suffix' => '%', 'series' => [[t('result.turnoutPct'), $ch['t']], [t('an.youngPct'), $ch['y']], [t('an.womenPct'), $ch['w']]]];
        return $r;
    }

    // ------------------------------------------------------------------ 10. campaign reach
    public function campaigns(?int $a): Report
    {
        $r = (new Report(t('report.campaign-reach'), $this->h('an.campaign', 'msg.channel', 'an.created', 'an.recipients', 'an.sent', 'an.failed', 'an.skipped',
            'an.pending', 'an.deliveredPct')))->subtitle($this->scope($a) . ' | ' . t('an.optOutsTotal', DB::table('message_optouts')->count()));
        $q = DB::table('message_outbox as o')->join('message_campaigns as c', 'c.id', '=', 'o.campaign_id')
            ->leftJoin('voters as v', 'v.id', '=', 'o.voter_id')
            ->when($a !== null, fn ($q) => $q->where('v.assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('v.ward_no', $this->ward))
            ->selectRaw("c.id, c.name, c.channel, c.created_at, count(*) as n, sum(case when o.status = 'SENT' then 1 else 0 end) as sent,
                sum(case when o.status = 'FAILED' then 1 else 0 end) as failed, sum(case when o.status = 'SKIPPED' then 1 else 0 end) as skipped,
                sum(case when o.status in ('QUEUED', 'SENDING') then 1 else 0 end) as pending")
            ->groupBy('c.id', 'c.name', 'c.channel', 'c.created_at')->orderByDesc('c.created_at')->get();
        $ch = ['l' => [], 's' => [], 'f' => []];
        foreach ($q as $x) {
            $r->add([$x->name, $x->channel, $x->created_at ? substr((string) $x->created_at, 0, 16) : '', (int) $x->n, (int) $x->sent, (int) $x->failed, (int) $x->skipped,
                (int) $x->pending, self::pct((int) $x->sent, (int) $x->n)]);
            $ch['l'][] = mb_substr($x->name, 0, 24); $ch['s'][] = (int) $x->sent; $ch['f'][] = (int) $x->failed;
        }
        $r->chart = ['type' => 'bar', 'stacked' => true, 'labels' => array_reverse($ch['l']), 'series' => [[t('an.sent'), array_reverse($ch['s']), 'support'], [t('an.failed'), array_reverse($ch['f']), 'opposition']]];
        return $r;
    }

    // ------------------------------------------------------------------ grievances
    public function grievances(?int $a): Report
    {
        $cats = \App\Models\Grievance::CATEGORIES;
        $r = (new Report(t('report.grievances'), $this->h('col.boothNo', 'gr.total', 'gr.status.OPEN', 'gr.status.IN_PROGRESS', 'gr.status.RESOLVED', 'gr.status.CLOSED',
            'gr.resolvedPct', 'gr.avgDays', 'gr.oldestOpen', 'gr.topCategory')))->subtitle($this->scope($a));
        $rows = DB::table('grievances')->when($a !== null, fn ($q) => $q->where('assembly_no', $a))->when($this->ward !== null, fn ($q) => $q->where('ward_no', $this->ward))
            ->get(['booth_no', 'status', 'category', 'created_at', 'resolved_at']);
        $by = []; $catStatus = [];
        foreach ($rows as $g) {
            $k = $g->booth_no ?? 0;
            $by[$k] ??= ['n' => 0, 'OPEN' => 0, 'IN_PROGRESS' => 0, 'RESOLVED' => 0, 'CLOSED' => 0, 'days' => [], 'oldest' => 0, 'cat' => []];
            $x = &$by[$k]; $x['n']++; $x[$g->status] = ($x[$g->status] ?? 0) + 1; $x['cat'][$g->category] = ($x['cat'][$g->category] ?? 0) + 1;
            $days = (int) floor(((($g->resolved_at ? strtotime($g->resolved_at) : time())) - strtotime((string) $g->created_at)) / 86400);
            if ($g->resolved_at) $x['days'][] = $days; elseif (in_array($g->status, ['OPEN', 'IN_PROGRESS'], true)) $x['oldest'] = max($x['oldest'], $days);
            unset($x);
            $open = in_array($g->status, ['OPEN', 'IN_PROGRESS'], true) ? 'open' : 'done';
            $catStatus[$g->category][$open] = ($catStatus[$g->category][$open] ?? 0) + 1;
        }
        ksort($by);
        $t = ['n' => 0, 'OPEN' => 0, 'IN_PROGRESS' => 0, 'RESOLVED' => 0, 'CLOSED' => 0, 'days' => [], 'oldest' => 0];
        foreach ($by as $b => $x) {
            arsort($x['cat']); $done = $x['RESOLVED'] + $x['CLOSED'];
            $r->add([$b ?: '-', $x['n'], $x['OPEN'], $x['IN_PROGRESS'], $x['RESOLVED'], $x['CLOSED'], self::pct($done, $x['n']),
                $x['days'] ? round(array_sum($x['days']) / count($x['days']), 1) : null, $x['oldest'] ?: null, t('gr.cat.' . array_key_first($x['cat']))]);
            foreach (['n', 'OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'] as $k) $t[$k] += $x[$k];
            $t['days'] = array_merge($t['days'], $x['days']); $t['oldest'] = max($t['oldest'], $x['oldest']);
        }
        $r->totals = [t('report.total'), $t['n'], $t['OPEN'], $t['IN_PROGRESS'], $t['RESOLVED'], $t['CLOSED'], self::pct($t['RESOLVED'] + $t['CLOSED'], $t['n']),
            $t['days'] ? round(array_sum($t['days']) / count($t['days']), 1) : null, $t['oldest'] ?: null, ''];
        uksort($catStatus, fn ($x, $y) => array_sum($catStatus[$y]) <=> array_sum($catStatus[$x]));
        $r->chart = ['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'labels' => array_map(fn ($c) => t('gr.cat.' . $c), array_keys($catStatus)), 'series' => [
            [t('gr.pending'), array_map(fn ($c) => $c['open'] ?? 0, array_values($catStatus)), 'opposition'], [t('gr.done'), array_map(fn ($c) => $c['done'] ?? 0, array_values($catStatus)), 'support']]];
        return $r;
    }

    // ------------------------------------------------------------------ 11. team activity
    public function team(): Report
    {
        $from = now()->subDays(30);
        $r = (new Report(t('report.team-activity'), $this->h('an.user', 'an.logins', 'an.creates', 'an.updates', 'an.deletes', 'an.imports', 'an.messages', 'an.lastActive')))
            ->subtitle(t('an.last30'));
        $q = DB::table('audit_log')->where('at', '>=', $from)->whereNotNull('username')->where('username', '<>', 'system')
            ->selectRaw("username, sum(case when action = 'LOGIN' then 1 else 0 end) as login, sum(case when action = 'CREATE' then 1 else 0 end) as cr,
                sum(case when action = 'UPDATE' then 1 else 0 end) as up, sum(case when action = 'DELETE' then 1 else 0 end) as del,
                sum(case when action = 'IMPORT' then 1 else 0 end) as imp, sum(case when action like 'MESSAGE%' or entity = 'CAMPAIGN' then 1 else 0 end) as msg, max(at) as last")
            ->groupBy('username')->orderByDesc('last')->get();
        foreach ($q as $x) $r->add([$x->username, (int) $x->login, (int) $x->cr, (int) $x->up, (int) $x->del, (int) $x->imp, (int) $x->msg, substr((string) $x->last, 0, 16)]);
        $r->chart = ['type' => 'bar', 'stacked' => true, 'labels' => $q->pluck('username')->all(), 'series' => [
            [t('an.creates'), $q->pluck('cr')->map(fn ($v) => (int) $v)->all()], [t('an.updates'), $q->pluck('up')->map(fn ($v) => (int) $v)->all()],
            [t('an.imports'), $q->pluck('imp')->map(fn ($v) => (int) $v)->all()]]];
        return $r;
    }

    // ------------------------------------------------------------------ analytics home
    /** Key figures and chart data for the Analytics home page. */
    public function summary(?int $a): array
    {
        $this->reports->ward = $this->ward;
        $tot = ['S' => 0, 'N' => 0, 'O' => 0, 'X' => 0, 'U' => 0, 'total' => 0];
        foreach ($this->sentimentByBooth($a) as $s) foreach ($tot as $k => $v) $tot[$k] += $s[$k];
        $c = $this->base($a)->selectRaw("count(*) as n, sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as m,
            sum(case when caste is not null and caste <> '' then 1 else 0 end) as ca, sum(" . self::WORKER_SQL . ") as w,
            sum(case when (beneficiary1 is not null and beneficiary1 <> '') or (beneficiary2 is not null and beneficiary2 <> '') or (beneficiary3 is not null and beneficiary3 <> '') then 1 else 0 end) as ben")->first();
        $age = array_fill(0, 6, ['MALE' => 0, 'FEMALE' => 0]); $bands = [[18, 25], [26, 35], [36, 45], [46, 60], [61, 75], [76, 200]];
        foreach ($this->base($a)->selectRaw('age, gender, count(*) as c')->whereNotNull('age')->groupBy('age', 'gender')->get() as $x) {
            foreach ($bands as $i => $bd) if ($x->age >= $bd[0] && $x->age <= $bd[1] && isset($age[$i][$x->gender])) { $age[$i][$x->gender] += (int) $x->c; break; }
        }
        $sc = $this->boothScorecard($a, Election::where('current', true)->first(), null);
        $grades = ['A' => 0, 'B' => 0, 'C' => 0, '-' => 0];
        foreach ($sc->rows as $row) $grades[$row[13]]++;
        $ben = $this->beneficiaries($a, null);
        $n = (int) $c->n; $surveyed = $tot['total'] - $tot['U'];
        return [
            'tiles' => [
                ['an.kpi.voters', $n, '/voters'],
                ['an.kpi.surveyed', $surveyed . ' (' . self::pct($surveyed, $n) . '%)', '/reports/survey-coverage'],
                ['an.kpi.support', $tot['S'] . ' (' . self::pct($tot['S'], $surveyed) . '%)', '/voters?sentiment=S'],
                ['an.kpi.boothsA', $grades['A'] . ' / ' . array_sum($grades), '/reports/booth-scorecard'],
                ['an.kpi.mobile', (int) $c->m . ' (' . self::pct((int) $c->m, $n) . '%)', '/voters?hasMobile=true'],
                ['an.kpi.caste', self::pct((int) $c->ca, $n) . '%', '/reports/survey-coverage'],
                ['an.kpi.beneficiaries', (int) $c->ben, '/reports/beneficiaries'],
                ['an.kpi.workers', (int) $c->w, '/reports/worker-coverage'],
            ],
            'charts' => [
                'sentiment' => ['type' => 'doughnut', 'labels' => $this->h('an.support', 'an.neutral', 'an.opposition', 'an.other', 'an.unknown'),
                    'series' => [[t('dash.voters'), [$tot['S'], $tot['N'], $tot['O'], $tot['X'], $tot['U']]]], 'roles' => ['support', 'neutral', 'opposition', 'other', 'unknown']],
                'grades' => ['type' => 'doughnut', 'labels' => [t('an.gradeA'), t('an.gradeB'), t('an.gradeC'), t('an.gradeNone')],
                    'series' => [[t('report.booths'), [$grades['A'], $grades['B'], $grades['C'], $grades['-']]]], 'roles' => ['support', 'neutral', 'opposition', 'unknown']],
                'age' => ['type' => 'bar', 'horizontal' => true, 'labels' => ['18-25', '26-35', '36-45', '46-60', '61-75', '76+'],
                    'series' => [[t('gender.MALE'), array_column($age, 'MALE'), 'male'], [t('gender.FEMALE'), array_column($age, 'FEMALE'), 'female']]],
                'benefits' => $ben->chart,
                'coverage' => $this->coverage($a)->chart,
            ],
        ];
    }
}
