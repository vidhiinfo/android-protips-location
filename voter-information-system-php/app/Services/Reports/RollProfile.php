<?php

namespace App\Services\Reports;

use App\Services\Voters\FamilyService;
use App\Support\Text;

/**
 * Analyses that need nothing but the electoral roll (no survey, no past results):
 * demographic profile per booth and booth outreach priority. Used by AnalyticsService.
 */
trait RollProfile
{
    /** Age bands of the demographic profile: [label key, from, to]. */
    public const AGE_BANDS = [['an.age18', 18, 19], ['an.age20', 20, 29], ['an.age30', 30, 39], ['an.age40', 40, 49], ['an.age50', 50, 59], ['an.age60', 60, 79], ['an.age80', 80, 200]];
    /** Outreach priority weights (points out of 100). */
    public const W_SIZE = 35, W_YOUNG = 20, W_SURVEY = 25, W_WORKERS = 20;

    /** Per booth: men / women / third, age bands, average age, ward (one grouped query). */
    private function rollCounts(?int $a): array
    {
        $bands = '';
        foreach (self::AGE_BANDS as $i => [, $lo, $hi]) $bands .= ", sum(case when age between $lo and $hi then 1 else 0 end) as b$i";
        $rows = $this->base($a)->selectRaw("assembly_no, booth_no, max(ward_no) as ward, count(*) as total,
            sum(case when gender = 'MALE' then 1 else 0 end) as m, sum(case when gender = 'FEMALE' then 1 else 0 end) as f,
            sum(case when gender = 'OTHER' then 1 else 0 end) as o, avg(case when age between 18 and 120 then age end) as avg_age,
            sum(case when political_status is not null and political_status <> '' then 1 else 0 end) as surveyed,
            sum(" . self::WORKER_SQL . ") as w$bands")->groupBy('assembly_no', 'booth_no')->orderBy('assembly_no')->orderBy('booth_no')->get();
        $out = [];
        foreach ($rows as $x) $out[$x->assembly_no . '/' . $x->booth_no] = $x;
        return $out;
    }

    /**
     * Houses and families per booth. Voters are read house by house; each house is split into families
     * (FamilyService) and a house / family counts in the booth of its first member.
     */
    public function familyStats(?int $a): array
    {
        $fam = app(FamilyService::class);
        $out = []; $key = null; $house = [];
        $flush = function () use (&$house, &$out, $fam) {
            if (!$house) return;
            $first = $house[0]; $k = $first->assembly_no . '/' . $first->booth_no;
            $out[$k] ??= ['houses' => 0, 'families' => 0, 'multi' => 0, 'single' => 0, 'big' => 0, 'members' => 0];
            $families = trim((string) $first->house_no) === '' ? array_map(fn ($v) => [$v], $house) : $fam->split($house);   // no house no.: nobody to link with
            $out[$k]['houses'] += trim((string) $first->house_no) === '' ? count($house) : 1;
            $out[$k]['families'] += count($families);
            if (trim((string) $first->house_no) !== '' && count($families) > 1) $out[$k]['multi']++;
            foreach ($families as $f) { $n = count($f); $out[$k]['members'] += $n; if ($n === 1) $out[$k]['single']++; if ($n >= 6) $out[$k]['big']++; }
            $house = [];
        };
        $q = $this->base($a)->select(['id', 'assembly_no', 'booth_no', 'serial_no', 'house_no', 'address', 'village_ward', 'name', 'relative_name', 'relation_type', 'gender', 'age'])
            ->orderBy('assembly_no')->orderByRaw(Text::HOUSE_KEY_SQL)->orderByRaw("lower(trim(coalesce(address, '')))")->orderByRaw("lower(trim(coalesce(village_ward, '')))")
            ->orderBy('booth_no')->orderBy('serial_no');
        foreach ($q->cursor() as $v) {
            $k = $v->assembly_no . '|' . Text::houseKey($v->house_no) . '|' . mb_strtolower(trim((string) $v->address)) . '|' . mb_strtolower(trim((string) $v->village_ward));
            if ($k !== $key) { $flush(); $key = $k; }
            $house[] = $v;
        }
        $flush();
        return $out;
    }

    // ------------------------------------------------------------------ demographic profile
    public function profile(?int $a): Report
    {
        $bandHeads = array_map(fn ($b) => $b[0], self::AGE_BANDS);
        $r = (new Report(t('report.demographic-profile'), $this->h('col.wardNo', 'col.boothNo', 'col.boothName', 'dash.voters', 'an.men', 'an.women', 'an.third',
            'an.womenPct', 'an.genderRatio', ...$bandHeads, ...['an.avgAge', 'an.households', 'an.families', 'an.avgFamily', 'an.multiFamily'])))->subtitle($this->scope($a));
        $counts = $this->rollCounts($a); $fam = $this->familyStats($a); $names = $this->boothNames($a);
        $nb = count(self::AGE_BANDS);
        $t = ['total' => 0, 'm' => 0, 'f' => 0, 'o' => 0, 'ageSum' => 0.0, 'houses' => 0, 'families' => 0, 'members' => 0, 'multi' => 0] + array_fill_keys(array_map(fn ($i) => "b$i", range(0, $nb - 1)), 0);
        foreach ($counts as $k => $x) {
            $f = $fam[$k] ?? ['houses' => 0, 'families' => 0, 'members' => 0, 'multi' => 0];
            $n = (int) $x->total; $bands = array_map(fn ($i) => (int) $x->{"b$i"}, range(0, $nb - 1));
            $link = ['assemblyNo' => (int) $x->assembly_no, 'wardNo' => $this->ward, 'boothNo' => (int) $x->booth_no];
            $r->add(array_merge([$x->ward !== null ? (int) $x->ward : '', (int) $x->booth_no, $names[$k] ?? '', $n, (int) $x->m, (int) $x->f, (int) $x->o,
                self::pct((int) $x->f, $n), $x->m ? (int) round(1000 * $x->f / $x->m) : null], $bands,
                [$x->avg_age !== null ? round((float) $x->avg_age, 1) : null, $f['houses'], $f['families'], $f['families'] ? round($f['members'] / $f['families'], 1) : 0.0, $f['multi']]),
                [3 => $link, 4 => $link + ['gender' => 'MALE'], 5 => $link + ['gender' => 'FEMALE']]);
            foreach (['total', 'm', 'f', 'o'] as $c) $t[$c] += (int) $x->$c;
            foreach ($bands as $i => $v) $t["b$i"] += $v;
            $t['ageSum'] += (float) $x->avg_age * $n;
            foreach (['houses', 'families', 'members', 'multi'] as $c) $t[$c] += $f[$c];
        }
        $r->totals = array_merge([t('report.total'), '', '', $t['total'], $t['m'], $t['f'], $t['o'], self::pct($t['f'], $t['total']), $t['m'] ? (int) round(1000 * $t['f'] / $t['m']) : null],
            array_map(fn ($i) => $t["b$i"], range(0, $nb - 1)),
            [$t['total'] ? round($t['ageSum'] / $t['total'], 1) : null, $t['houses'], $t['families'], $t['families'] ? round($t['members'] / $t['families'], 1) : 0.0, $t['multi']]);
        $r->chart = ['type' => 'bar', 'labels' => array_map(fn ($h) => t($h), $bandHeads), 'series' => [[t('dash.voters'), array_map(fn ($i) => $t["b$i"], range(0, $nb - 1))]], 'axisX' => t('col.age')];
        return $r;
    }

    // ------------------------------------------------------------------ booth outreach priority
    public function outreach(?int $a): Report
    {
        $r = (new Report(t('report.outreach-priority'), $this->h('an.rank', 'col.wardNo', 'col.boothNo', 'col.boothName', 'dash.voters', 'an.young29Pct', 'an.age18',
            'an.age80', 'an.surveyedPct', 'an.workers', 'an.votersPerWorker', 'an.score', 'an.focus')))->subtitle($this->scope($a));
        $counts = $this->rollCounts($a); $names = $this->boothNames($a);
        if (!$counts) return $r;
        $maxN = max(array_map(fn ($x) => (int) $x->total, $counts)) ?: 1;
        $young = fn ($x) => (int) $x->b0 + (int) $x->b1;   // 18-29
        $maxYoung = max(array_map(fn ($x) => $x->total ? $young($x) / $x->total : 0, $counts)) ?: 1;
        $rows = [];
        foreach ($counts as $k => $x) {
            $n = (int) $x->total; $w = (int) $x->w; $vpw = $w ? (int) round($n / $w) : null;
            $surv = self::pct((int) $x->surveyed, $n); $yp = $n ? $young($x) / $n : 0;
            $gap = $w ? min(1, max(0, ($vpw - self::VOTERS_PER_WORKER) / self::VOTERS_PER_WORKER)) : 1;
            $score = round(self::W_SIZE * $n / $maxN + self::W_YOUNG * $yp / $maxYoung + self::W_SURVEY * (1 - $surv / 100) + self::W_WORKERS * $gap, 1);
            $focus = [];
            if ($gap >= 0.5) $focus[] = t('an.focus.workers');
            if ($surv < self::MIN_SURVEYED_PCT) $focus[] = t('an.focus.survey');
            if ($yp >= 0.9 * $maxYoung || (int) $x->b0 >= 40) $focus[] = t('an.focus.young');
            if ((int) $x->b6 >= 20) $focus[] = t('an.focus.senior');
            $rows[] = ['k' => $k, 'x' => $x, 'n' => $n, 'yp' => round(100 * $yp, 1), 'surv' => $surv, 'w' => $w, 'vpw' => $vpw, 'score' => $score, 'focus' => implode(', ', $focus) ?: t('an.focus.keep')];
        }
        usort($rows, fn ($p, $q) => $q['score'] <=> $p['score'] ?: $p['x']->booth_no <=> $q['x']->booth_no);
        foreach ($rows as $i => $o) {
            $x = $o['x']; $link = ['assemblyNo' => (int) $x->assembly_no, 'wardNo' => $this->ward, 'boothNo' => (int) $x->booth_no];
            $r->add([$i + 1, $x->ward !== null ? (int) $x->ward : '', (int) $x->booth_no, $names[$o['k']] ?? '', $o['n'], $o['yp'], (int) $x->b0, (int) $x->b6,
                $o['surv'], $o['w'], $o['vpw'], $o['score'], $o['focus']], [4 => $link]);
        }
        $top = array_slice($rows, 0, 20);
        $r->chart = ['type' => 'bar', 'horizontal' => true, 'labels' => array_map(fn ($o) => t('an.boothShort', $o['x']->booth_no), $top), 'series' => [[t('an.score'), array_column($top, 'score')]]];
        $r->note = t('an.note.outreach-priority', self::W_SIZE, self::W_YOUNG, self::W_SURVEY, self::W_WORKERS, self::VOTERS_PER_WORKER);
        return $r;
    }
}
