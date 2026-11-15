<?php

namespace App\Services\Results;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\BoothResult;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Voter;
use App\Services\Auth\AuditService;
use Illuminate\Support\Facades\DB;

class ResultService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Result of one seat: totals per candidate (sorted, with share and rank), booth rows, winner, margin, turnout.
     */
    /** Seat result; with $ward only the booths of that ward are counted. */
    public function contest(Election $e, int $assemblyNo, ?int $ward = null): array
    {
        $cands = Candidate::with('party')->where('election_id', $e->id)->where('assembly_no', $assemblyNo)
            ->orderByRaw('ballot_no is null')->orderBy('ballot_no')->orderBy('name')->get();
        $wardBooths = $ward === null ? null
            : Voter::where('assembly_no', $assemblyNo)->where('ward_no', $ward)->distinct()->pluck('booth_no')->map(fn ($b) => (int) $b)->all();
        $rows = BoothResult::where('election_id', $e->id)->where('assembly_no', $assemblyNo)
            ->when($wardBooths !== null, fn ($q) => $q->whereIn('booth_no', $wardBooths ?: [0]))->get();
        $perCand = [];
        $perBooth = [];
        foreach ($rows as $r) {
            $perCand[$r->candidate_id] = ($perCand[$r->candidate_id] ?? 0) + $r->votes;
            $perBooth[$r->booth_no][$r->candidate_id] = $r->votes;
        }
        ksort($perBooth);
        $total = array_sum($perCand);
        $sorted = $cands->sortByDesc(fn ($c) => $perCand[$c->id] ?? 0)->values();
        $totals = [];
        foreach ($sorted as $i => $c) {
            $v = $perCand[$c->id] ?? 0;
            $totals[] = ['candidate' => $c, 'votes' => $v, 'share' => $total ? 100 * $v / $total : 0, 'rank' => $i + 1];
        }
        $boothNames = Booth::where('assembly_no', $assemblyNo)->pluck('name', 'booth_no')->all();
        $byId = $cands->keyBy('id');
        $booths = [];
        foreach ($perBooth as $no => $votes) {
            arsort($votes);
            $leaderId = array_key_first($votes);
            $booths[] = ['booth_no' => $no, 'booth_name' => $boothNames[$no] ?? '', 'votes' => $perBooth[$no], 'total' => array_sum($votes),
                'leader' => $leaderId ? $byId[$leaderId] ?? null : null];
        }
        $boothsTotal = $wardBooths !== null ? count($wardBooths)
            : ($boothNames ? count($boothNames) : Voter::where('assembly_no', $assemblyNo)->distinct()->count('booth_no'));
        $electors = Voter::where('assembly_no', $assemblyNo)->when($ward !== null, fn ($q) => $q->where('ward_no', $ward))->count();
        $winner = $totals && $total ? $totals[0] : null;
        $runner = count($totals) > 1 && $total ? $totals[1] : null;
        return [
            'election' => $e, 'assembly_no' => $assemblyNo, 'ward_no' => $ward, 'assembly_name' => Assembly::where('assembly_no', $assemblyNo)->value('name') ?? '',
            'totals' => $totals, 'total_votes' => $total, 'electors' => $electors, 'booths' => $booths,
            'booths_reported' => count($perBooth), 'booths_total' => $boothsTotal,
            'winner' => $winner, 'runner_up' => $runner,
            'margin' => $winner ? $winner['votes'] - ($runner['votes'] ?? 0) : 0,
            'turnout' => $electors ? 100 * $total / $electors : 0,
            'complete' => $boothsTotal > 0 && count($perBooth) >= $boothsTotal,
        ];
    }

    public function seats(Election $e): array
    {
        return Candidate::where('election_id', $e->id)->distinct()->orderBy('assembly_no')->pluck('assembly_no')->all();
    }

    public function electionContests(Election $e, ?int $only = null): array
    {
        $out = [];
        foreach ($this->seats($e) as $a) if ($only === null || $only === (int) $a) $out[] = $this->contest($e, (int) $a);
        return $out;
    }

    public function boothEntry(int $electionId, int $assemblyNo, int $boothNo): array
    {
        $existing = BoothResult::where('election_id', $electionId)->where('assembly_no', $assemblyNo)->where('booth_no', $boothNo)
            ->pluck('votes', 'candidate_id')->all();
        $out = [];
        foreach (Candidate::with('party')->where('election_id', $electionId)->where('assembly_no', $assemblyNo)
                     ->orderByRaw('ballot_no is null')->orderBy('ballot_no')->orderBy('name')->get() as $c) {
            $out[] = ['candidate' => $c, 'votes' => $existing[$c->id] ?? null];
        }
        return $out;
    }

    public function reportedBooths(int $electionId, int $assemblyNo): array
    {
        return BoothResult::where('election_id', $electionId)->where('assembly_no', $assemblyNo)->distinct()->pluck('booth_no')->all();
    }

    public function saveBooth(int $electionId, int $assemblyNo, int $boothNo, array $votes, string $user): void
    {
        $valid = Candidate::where('election_id', $electionId)->where('assembly_no', $assemblyNo)->pluck('id')->all();
        $total = 0;
        DB::transaction(function () use ($votes, $valid, $electionId, $assemblyNo, $boothNo, $user, &$total) {
            foreach ($votes as $cid => $v) {
                if (!in_array((int) $cid, $valid, true) || $v === null || $v === '') continue;
                $v = max(0, (int) \App\Support\Text::asciiDigits((string) $v));
                BoothResult::updateOrCreate(['candidate_id' => (int) $cid, 'booth_no' => $boothNo],
                    ['election_id' => $electionId, 'assembly_no' => $assemblyNo, 'votes' => $v, 'entered_by' => $user, 'updated_at' => now()]);
                $total += $v;
            }
        });
        $this->audit->log('RESULT_SAVE', 'RESULT', "$electionId/$assemblyNo/$boothNo", "total votes $total");
    }
}
