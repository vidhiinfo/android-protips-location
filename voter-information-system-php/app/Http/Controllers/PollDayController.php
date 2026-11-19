<?php

namespace App\Http\Controllers;

use App\Models\Booth;
use App\Models\Election;
use App\Models\PollMark;
use App\Models\Voter;
use App\Services\Reports\AnalyticsService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Poll day: booth agents mark voters who have voted (by serial no. or EPIC, or by tapping the serial grid);
 * the war room sees live turnout per booth and the supporters who have not voted yet.
 */
class PollDayController extends Controller
{
    public function __construct(private VoterService $voters) {}

    private function election(): ?Election { return Election::where('current', true)->first(); }

    private function place(Request $r): array
    {
        $a = $this->user()->restrict($r->input('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $booths = $a ? $this->voters->booths($a) : [];
        $b = $r->input('boothNo') && in_array((int) $r->input('boothNo'), $booths) ? (int) $r->input('boothNo') : null;
        return [$a, $b, $booths];
    }

    private function alive() { return Voter::query()->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted')); }

    /** Turnout of one booth: voters, voted, supporters, supporters voted. */
    public function boothStats(Election $e, int $a, int $b): array
    {
        $x = $this->alive()->where('assembly_no', $a)->where('booth_no', $b)->selectRaw("count(*) as n,
            sum(case when " . AnalyticsService::SENTIMENT_SQL . " = 'S' then 1 else 0 end) as s,
            sum(case when exists (select 1 from poll_marks p where p.voter_id = voters.id and p.election_id = ?) then 1 else 0 end) as v,
            sum(case when " . AnalyticsService::SENTIMENT_SQL . " = 'S' and exists (select 1 from poll_marks p where p.voter_id = voters.id and p.election_id = ?) then 1 else 0 end) as sv", [$e->id, $e->id])->first();
        return ['n' => (int) $x->n, 'voted' => (int) $x->v, 'support' => (int) $x->s, 'supportVoted' => (int) $x->sv];
    }

    public function index(Request $r)
    {
        [$a, $b, $booths] = $this->place($r);
        $e = $this->election();
        $recent = $e && $b ? PollMark::where('election_id', $e->id)->where('assembly_no', $a)->where('booth_no', $b)->orderByDesc('marked_at')->orderByDesc('id')->limit(15)->get() : collect();
        $names = Voter::whereIn('id', $recent->pluck('voter_id'))->get()->keyBy('id');
        return view('pollday.index', ['e' => $e, 'assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'booths' => $booths, 'boothNo' => $b,
            'stats' => $e && $b ? $this->boothStats($e, $a, $b) : null, 'recent' => $recent, 'names' => $names,
            'boothName' => $b ? Booth::where('assembly_no', $a)->where('booth_no', $b)->value('name') : null]);
    }

    /** Mark by serial no. (in the chosen booth) or EPIC no. */
    public function mark(Request $r)
    {
        [$a, $b] = $this->place($r);
        $e = $this->election();
        $back = '/pollday?' . http_build_query(['assemblyNo' => $a, 'boothNo' => $b]);
        if (!$e || !$b) return redirect($back)->with('error', 'poll.noElection');
        $q = strtoupper(trim((string) $r->input('q')));
        $v = ctype_digit($q) ? Voter::where('assembly_no', $a)->where('booth_no', $b)->where('serial_no', (int) $q)->first()
            : ($q !== '' ? Voter::where('assembly_no', $a)->where('epic_no', $q)->first() : null);
        if (!$v) return redirect($back)->with('error', t('poll.notFound', $q));
        return redirect($back)->with(...$this->doMark($e, $v));
    }

    private function doMark(Election $e, Voter $v): array
    {
        $m = PollMark::where('election_id', $e->id)->where('voter_id', $v->id)->first();
        $who = $v->serial_no . ' - ' . $v->name . ($v->booth_no ? ' (' . t('col.boothNo') . ' ' . $v->booth_no . ')' : '');
        if ($m) return ['error', t('poll.already', $who, $m->marked_at->format('H:i'), $m->marked_by)];
        PollMark::create(['election_id' => $e->id, 'voter_id' => $v->id, 'assembly_no' => $v->assembly_no, 'booth_no' => $v->booth_no,
            'marked_by' => $this->user()->username(), 'marked_at' => now()]);
        return ['success', t('poll.marked', $who)];
    }

    public function unmark(Request $r)
    {
        [$a, $b] = $this->place($r);
        $e = $this->election();
        if ($e) PollMark::where('election_id', $e->id)->where('voter_id', (int) $r->input('voter_id'))->where('assembly_no', $a)->delete();
        $this->audit()->log('DELETE', 'POLL', (int) $r->input('voter_id'), 'poll-day mark removed');
        return redirect('/pollday?' . http_build_query(['assemblyNo' => $a, 'boothNo' => $b]))->with('success', 'poll.unmarked');
    }

    /** Serial grid for a booth: tap a number to mark / unmark (supporters outlined). */
    public function grid(Request $r)
    {
        [$a, $b, $booths] = $this->place($r);
        $e = $this->election();
        $rows = $e && $b ? $this->alive()->where('assembly_no', $a)->where('booth_no', $b)->orderBy('serial_no')
            ->selectRaw('id, serial_no, name, ' . AnalyticsService::SENTIMENT_SQL . " as st,
                exists (select 1 from poll_marks p where p.voter_id = voters.id and p.election_id = ?) as voted", [$e->id])->get() : collect();
        return view('pollday.grid', ['e' => $e, 'assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'booths' => $booths, 'boothNo' => $b, 'rows' => $rows]);
    }

    public function toggle(Request $r)
    {
        $e = $this->election();
        $v = Voter::find((int) $r->input('voter_id'));
        abort_if(!$e || !$v || !$this->user()->allows($v->assembly_no), 404);
        $m = PollMark::where('election_id', $e->id)->where('voter_id', $v->id)->first();
        if ($m) $m->delete();
        else PollMark::create(['election_id' => $e->id, 'voter_id' => $v->id, 'assembly_no' => $v->assembly_no, 'booth_no' => $v->booth_no,
            'marked_by' => $this->user()->username(), 'marked_at' => now()]);
        return response()->json(['voted' => !$m, 'stats' => $this->boothStats($e, $v->assembly_no, $v->booth_no)]);
    }

    /** War room: live turnout of every booth, supporters still to vote, votes per hour. */
    public function live(Request $r)
    {
        [$a] = $this->place($r);
        $w = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        $e = $this->election();
        $rows = []; $tot = ['n' => 0, 'voted' => 0, 'support' => 0, 'supportVoted' => 0]; $hours = [];
        if ($e && $a) {
            $q = $this->alive()->where('assembly_no', $a)->when($w !== null, fn ($q) => $q->where('ward_no', $w))->selectRaw("booth_no, count(*) as n,
                sum(case when " . AnalyticsService::SENTIMENT_SQL . " = 'S' then 1 else 0 end) as s,
                sum(case when exists (select 1 from poll_marks p where p.voter_id = voters.id and p.election_id = ?) then 1 else 0 end) as v,
                sum(case when " . AnalyticsService::SENTIMENT_SQL . " = 'S' and exists (select 1 from poll_marks p where p.voter_id = voters.id and p.election_id = ?) then 1 else 0 end) as sv", [$e->id, $e->id])
                ->groupBy('booth_no')->orderBy('booth_no')->get();
            $last = PollMark::where('election_id', $e->id)->where('assembly_no', $a)->groupBy('booth_no')->selectRaw('booth_no, max(marked_at) as t')->pluck('t', 'booth_no');
            $names = Booth::where('assembly_no', $a)->pluck('name', 'booth_no');
            foreach ($q as $x) {
                $row = ['b' => (int) $x->booth_no, 'name' => $names[$x->booth_no] ?? '', 'n' => (int) $x->n, 'voted' => (int) $x->v, 'support' => (int) $x->s,
                    'supportVoted' => (int) $x->sv, 'last' => $last[$x->booth_no] ?? null];
                $rows[] = $row; foreach ($tot as $k => $_) $tot[$k] += $row[$k];
            }
            foreach (PollMark::where('election_id', $e->id)->where('assembly_no', $a)->get(['marked_at', 'booth_no']) as $m) {
                if (!collect($rows)->contains('b', $m->booth_no)) continue;   // only booths of the selected ward / the user's area
                $h = (int) $m->marked_at->format('G'); $hours[$h] = ($hours[$h] ?? 0) + 1;
            }
            ksort($hours);
        }
        $chart = ['type' => 'bar', 'labels' => array_map(fn ($x) => (string) $x['b'], $rows), 'max' => 100, 'suffix' => '%', 'series' => [
            [t('poll.turnout'), array_map(fn ($x) => $x['n'] ? round(100 * $x['voted'] / $x['n'], 1) : 0, $rows), 'support'],
            [t('poll.supportTurnout'), array_map(fn ($x) => $x['support'] ? round(100 * $x['supportVoted'] / $x['support'], 1) : 0, $rows), 'other']]];
        $hourChart = ['type' => 'line', 'labels' => array_map(fn ($h) => sprintf('%02d:00', $h), array_keys($hours)), 'series' => [[t('poll.votesPerHour'), array_values($hours)]]];
        return view('pollday.live', ['e' => $e, 'assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'wards' => $a ? $this->voters->wards($a) : [], 'wardNo' => $w,
            'rows' => $rows, 'tot' => $tot, 'chart' => $chart, 'hourChart' => $hourChart]);
    }
}
