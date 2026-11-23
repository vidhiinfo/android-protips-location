<?php

namespace App\Services\Results;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\BoothResult;
use App\Models\Election;
use App\Services\Reports\AnalyticsService;

/**
 * Votes-to-win planner: for every booth, how many votes the party needs, what the survey says it has,
 * and the gap to close. Uses last election's booth results (Form 20) and the house-to-house survey.
 *
 * How the numbers are worked out (also shown on the page):
 *  - Expected votes = electors on today's roll x turnout (last election's booth turnout, or the turnout typed in).
 *  - Seat target share = best rival's share last time + safety margin (or the target typed in).
 *  - Swing needed = seat target share - our share last time; every booth's target = our share there last time + that swing.
 *  - Projected votes = supporters found in the survey x supporter turnout + voters not yet surveyed x turnout x our share there last time.
 *  - Gap = booth target - projected votes. Reachable = neutral voters + the rest of the not-surveyed voters who are expected to vote.
 */
class PlannerService
{
    public const STATUSES = ['swing', 'tough', 'track', 'safe'];
    /** Turnout when there is no past result to go by. */
    public const DEFAULT_TURNOUT = 65.0;
    /** Target share when there is no past result and none is typed in. */
    public const DEFAULT_TARGET = 40.0;
    private const MAX_TURNOUT = 95.0;
    /** Below this survey coverage (%) a booth's numbers rest mainly on last election's pattern. */
    public const MIN_SURVEY = 20.0;

    public function __construct(private ResultService $results, private AnalyticsService $analytics) {}

    /** Elections that have booth results for this seat, newest first. */
    public function pastElections(int $assemblyNo): array
    {
        $ids = BoothResult::where('assembly_no', $assemblyNo)->distinct()->pluck('election_id')->all();
        return Election::whereIn('id', $ids ?: [0])->orderByDesc('election_year')->orderByDesc('id')->get()->all();
    }

    /** Party short names that stood in the seat in that election. */
    public function parties(?Election $e, int $assemblyNo): array
    {
        if (!$e) return [];
        $c = $this->results->contest($e, $assemblyNo);
        $out = [];
        foreach ($c['totals'] as $t) if (!self::isNota($t['candidate'])) $out[$t['candidate']->partyShort()] = $t['candidate']->party?->name ?? $t['candidate']->partyShort();
        return $out;
    }

    private static function isNota($cand): bool
    {
        return strtoupper((string) $cand->partyShort()) === 'NOTA' || strtoupper(trim((string) $cand->name)) === 'NOTA';
    }

    /**
     * @param array $opt margin (points), supporter (supporter turnout %), turnout (% or null = from last election), target (% or null = auto)
     */
    public function plan(int $assemblyNo, ?Election $past, ?string $party, array $opt = [], ?int $ward = null): array
    {
        $margin = (float) ($opt['margin'] ?? config('voters.planner.safety_margin', 3));
        $supTurnout = (float) ($opt['supporter'] ?? config('voters.planner.supporter_turnout', 80));
        $turnoutIn = isset($opt['turnout']) && $opt['turnout'] !== null ? max(1.0, min(100.0, (float) $opt['turnout'])) : null;
        $targetIn = isset($opt['target']) && $opt['target'] !== null ? max(1.0, min(95.0, (float) $opt['target'])) : null;

        // ---- last election
        $seatPast = null; $boothPast = [];
        if ($past) {
            $c = $this->results->contest($past, $assemblyNo, $ward);
            if ($c['total_votes'] > 0) {
                $partyOf = []; $nota = [];
                foreach ($c['totals'] as $t) { $partyOf[$t['candidate']->id] = $t['candidate']->partyShort(); if (self::isNota($t['candidate'])) $nota[$t['candidate']->id] = true; }
                $known = array_values(array_unique(array_values(array_diff_key($partyOf, $nota))));
                $top = null;   // last time's winner, never NOTA
                foreach ($c['totals'] as $t) if (!isset($nota[$t['candidate']->id])) { $top = $t['candidate']->partyShort(); break; }
                $party = $party && in_array($party, $known, true) ? $party : $top;
                // seat: our votes and the best other candidate (NOTA is never the rival)
                $ours = 0; $rival = 0; $rivalParty = null; $rank = null;
                foreach ($c['totals'] as $t) {
                    if (isset($nota[$t['candidate']->id])) continue;
                    if ($partyOf[$t['candidate']->id] === $party) { $ours += $t['votes']; $rank ??= $t['rank']; }
                    elseif ($t['votes'] > $rival) { $rival = $t['votes']; $rivalParty = $partyOf[$t['candidate']->id]; }
                }
                $tot = $c['total_votes'];
                $seatPast = ['election' => $past, 'party' => $party, 'votes' => $ours, 'share' => 100 * $ours / $tot, 'rival_party' => $rivalParty,
                    'rival_votes' => $rival, 'rival_share' => 100 * $rival / $tot, 'total' => $tot, 'rank' => $rank, 'won' => $ours > $rival,
                    'margin' => $ours - $rival, 'booths_reported' => $c['booths_reported']];
                foreach ($c['booths'] as $bt) {
                    $o = 0; $rv = 0;
                    foreach ($bt['votes'] as $cid => $v) {
                        if (isset($nota[$cid])) continue;
                        if (($partyOf[$cid] ?? '') === $party) $o += $v; else $rv = max($rv, $v);
                    }
                    $boothPast[$bt['booth_no']] = ['ours' => $o, 'rival' => $rv, 'total' => $bt['total'], 'share' => $bt['total'] ? 100 * $o / $bt['total'] : 0.0];
                }
            }
        }

        // ---- the target
        $autoTarget = $seatPast ? $seatPast['rival_share'] + $margin : self::DEFAULT_TARGET;
        $targetShare = max(1.0, min(95.0, $targetIn ?? $autoTarget));
        $swing = $seatPast ? $targetShare - $seatPast['share'] : 0.0;
        $seatTurnoutPast = null;

        // ---- survey and today's roll, per booth (only booths this login may see)
        $this->analytics->ward = $ward;
        $survey = array_filter($this->analytics->sentimentByBooth($assemblyNo), fn ($s) => $s['a'] === $assemblyNo);
        $electorsAll = array_sum(array_map(fn ($s) => $s['total'], $survey));
        if ($seatPast && $electorsAll) {
            $pastElectorsSeen = 0; $pastVotesSeen = 0;
            foreach ($survey as $s) if (isset($boothPast[$s['b']])) { $pastElectorsSeen += $s['total']; $pastVotesSeen += $boothPast[$s['b']]['total']; }
            if ($pastElectorsSeen) $seatTurnoutPast = min(self::MAX_TURNOUT, 100 * $pastVotesSeen / $pastElectorsSeen);
        }
        $names = Booth::where('assembly_no', $assemblyNo)->pluck('name', 'booth_no')->all();

        $rows = [];
        $sum = ['electors' => 0, 'expected' => 0, 'target' => 0, 'projected' => 0, 'gap' => 0, 'reachable' => 0, 'S' => 0, 'N' => 0, 'O' => 0, 'U' => 0, 'surveyed' => 0,
            'past_ours' => 0, 'past_rival' => 0];
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($survey as $s) {
            $b = $s['b']; $E = $s['total'];
            if ($E <= 0) continue;
            $p = $boothPast[$b] ?? null;
            $T = $turnoutIn ?? ($p && $p['total'] ? min(self::MAX_TURNOUT, 100 * $p['total'] / $E) : ($seatTurnoutPast ?? self::DEFAULT_TURNOUT));
            $expected = (int) round($E * $T / 100);
            $bShare = $p ? max(0.0, min(95.0, $p['share'] + $swing)) : $targetShare;
            $target = (int) ceil($expected * $bShare / 100);
            $notSurveyed = $s['U'];
            $neutral = $s['N'] + $s['X'];   // an answer that is not support / opposition counts as neutral
            $pastShare = $p ? $p['share'] / 100 : 0.0;
            $projected = (int) round($s['S'] * $supTurnout / 100 + $notSurveyed * $T / 100 * $pastShare);
            $gap = max(0, $target - $projected);
            $reachable = (int) round($neutral * $T / 100 + $notSurveyed * $T / 100 * (1 - $pastShare));
            $status = $gap > 0 ? ($gap <= $reachable ? 'swing' : 'tough') : ($projected >= $target * 1.15 && $target > 0 ? 'safe' : 'track');
            $counts[$status]++;
            $surveyed = $E - $notSurveyed;
            $rows[] = ['booth_no' => $b, 'booth_name' => $names[$b] ?? '', 'electors' => $E, 'turnout' => $T, 'expected' => $expected,
                'past' => $p, 'past_margin' => $p ? $p['ours'] - $p['rival'] : null, 'target_share' => $bShare, 'target' => $target,
                'S' => $s['S'], 'N' => $neutral, 'O' => $s['O'], 'U' => $notSurveyed, 'surveyed_pct' => 100 * $surveyed / $E,
                'projected' => $projected, 'gap' => $gap, 'reachable' => $reachable, 'status' => $status,
                'progress' => $target ? min(100, 100 * $projected / $target) : 100];
            foreach (['electors' => $E, 'expected' => $expected, 'target' => $target, 'projected' => $projected, 'gap' => $gap, 'reachable' => $reachable,
                         'S' => $s['S'], 'N' => $neutral, 'O' => $s['O'], 'U' => $notSurveyed, 'surveyed' => $surveyed] as $k => $v) $sum[$k] += $v;
            if ($p) { $sum['past_ours'] += $p['ours']; $sum['past_rival'] += $p['rival']; }
        }
        // priority: winnable booths first (biggest gap first), then tough, on track, safe
        $order = array_flip(self::STATUSES);
        usort($rows, fn ($x, $y) => [$order[$x['status']], -$x['gap'], $x['booth_no']] <=> [$order[$y['status']], -$y['gap'], $y['booth_no']]);
        $seatGap = max(0, $sum['target'] - $sum['projected']);

        return [
            'assembly_no' => $assemblyNo, 'assembly_name' => Assembly::where('assembly_no', $assemblyNo)->value('name') ?? '', 'ward_no' => $ward,
            'party' => $party, 'past' => $seatPast, 'margin' => $margin, 'supporter_turnout' => $supTurnout, 'turnout_in' => $turnoutIn,
            'target_in' => $targetIn, 'target_share' => $targetShare, 'auto_target' => $autoTarget, 'swing' => $swing,
            'rows' => $rows, 'sum' => $sum, 'counts' => $counts, 'seat_gap' => $seatGap,
            'on_track' => $sum['target'] > 0 && $seatGap === 0,
            'surveyed_pct' => $sum['electors'] ? 100 * $sum['surveyed'] / $sum['electors'] : 0.0,
            'turnout' => $sum['electors'] ? 100 * $sum['expected'] / $sum['electors'] : 0.0,
        ];
    }
}
