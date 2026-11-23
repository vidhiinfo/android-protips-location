<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\PollMark;
use App\Models\Voter;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline field app (/field): a worker downloads his booths once ("booth pack"), then searches voters and marks
 * poll-day votes without internet. Every action goes into one outbox on the phone and is sent whenever there is signal.
 */
class FieldController extends Controller
{
    public function __construct(private VoterService $voters) {}

    private function election(): ?Election { return Election::where('current', true)->first(); }

    public function page(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $e = $this->election();
        return view('field.index', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'booths' => $a ? $this->voters->booths($a) : [],
            'election' => $e, 'keepDays' => (int) config('voters.survey.offline_days'), 'maxBooths' => (int) config('voters.survey.field_max_booths'),
            'canPoll' => $this->user()->can('POLL_DAY')]);
    }

    /** Booth numbers asked for, limited to the user's assembly / access area and to FIELD_MAX_BOOTHS. */
    private function pickBooths(Request $r, ?int &$a): array
    {
        $a = $this->user()->restrict($r->input('assemblyNo'));
        abort_unless($a, 404, t('error.notFound'));
        $allowed = $this->voters->booths($a);
        $want = array_values(array_unique(array_map('intval', (array) $r->input('booths', []))));
        return array_slice(array_values(array_intersect($want, $allowed)), 0, max(1, (int) config('voters.survey.field_max_booths')));
    }

    /** Leaning of a political status: S support, N neutral, O opposition, U not met, '' not surveyed. */
    private static function lean(?string $status, array $map): string
    {
        if ($status === null || $status === '') return '';
        return $map[mb_strtolower($status)] ?? 'N';
    }

    /** The booth pack: every voter of the chosen booths with what search and poll day need, plus today's marks. */
    public function pack(Request $r)
    {
        $booths = $this->pickBooths($r, $a);
        abort_unless($booths, 422, t('field.pickBooths'));
        $e = $this->election();
        $map = \App\Support\StatusMasters::leanings();
        $marked = $e ? PollMark::where('election_id', $e->id)->where('assembly_no', $a)->whereIn('booth_no', $booths)->pluck('voter_id')->flip()->all() : [];
        $names = DB::table('booths')->where('assembly_no', $a)->whereIn('booth_no', $booths)->pluck('name', 'booth_no');
        $out = []; $count = 0;
        foreach ($booths as $b) {
            $vs = [];
            foreach (Voter::where('assembly_no', $a)->where('booth_no', $b)->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))
                         ->when($this->user()->workerId(), fn ($q, $w) => $q->where('worker_id', $w))
                         ->orderByRaw('serial_no is null')->orderBy('serial_no')->orderBy('id')
                         ->get(['id', 'serial_no', 'epic_no', 'name', 'name_local', 'relative_name', 'relative_name_local', 'relation_type', 'gender', 'age',
                             'house_no', 'mobile', 'political_status', 'assembly_no', 'worker_id']) as $v) {
                if (!$this->user()->allowsVoter($v)) continue;
                $vs[] = ['id' => $v->id, 'sl' => $v->serial_no, 'epic' => $v->epic_no, 'n' => $v->name, 'nl' => $v->name_local, 'r' => $v->relative_name,
                    'rl' => $v->relative_name_local, 'rt' => $v->relation_type, 'g' => $v->gender, 'a' => $v->age, 'h' => (string) $v->house_no,
                    'm' => $v->mobile, 'l' => self::lean($v->political_status, $map), 'v' => isset($marked[$v->id]) ? 1 : 0];
            }
            $count += count($vs);
            $out[] = ['no' => $b, 'name' => (string) ($names[$b] ?? ''), 'voters' => $vs];
            $this->touch($a, $b, 'downloaded_at', 0);
        }
        $this->audit()->log('EXPORT', 'VOTER', null, "offline booth pack: assembly $a booths " . implode(', ', $booths) . ", $count voters");
        return response()->json([
            'assembly' => $a, 'assemblyName' => $this->voters->assemblies()[$a] ?? '', 'user' => $this->user()->username(), 'at' => now()->toIso8601String(),
            'keepDays' => (int) config('voters.survey.offline_days'), 'election' => $e ? ['id' => $e->id, 'name' => $e->name] : null,
            'canPoll' => $this->user()->can('POLL_DAY'), 'booths' => $out,
        ])->header('Cache-Control', 'no-store');
    }

    private function touch(int $a, int $b, string $col, int $items): void
    {
        if (!Schema::hasTable('field_syncs')) return;
        $vals = [$col => now()] + ($col === 'synced_at' ? ['last_items' => $items] : []);
        DB::table('field_syncs')->updateOrInsert(['username' => $this->user()->username(), 'assembly_no' => $a, 'booth_no' => $b], $vals);
    }

    /**
     * The phone's outbox: [{uid, type: mark|unmark, voter_id, election_id, at}] in the order they happened, plus the booths it holds.
     * Marks are idempotent (a voter is marked once per election); an "unmark" only removes a mark made before it.
     * Returns the accepted uids (removed from the phone) and the current marks of its booths (other agents' marks too).
     */
    public function sync(Request $r)
    {
        $items = array_slice((array) $r->input('items', []), 0, 3000);
        $e = $this->election();
        $done = []; $rejected = [];
        $canPoll = $this->user()->can('POLL_DAY');
        $ids = array_filter(array_map(fn ($i) => (int) ($i['voter_id'] ?? 0), $items));
        $voters = Voter::whereIn('id', $ids ?: [0])->get(['id', 'assembly_no', 'booth_no', 'worker_id'])->keyBy('id');
        $per = [];
        foreach ($items as $i) {
            $uid = substr((string) ($i['uid'] ?? ''), 0, 64);
            if ($uid === '') continue;
            $type = (string) ($i['type'] ?? '');
            $v = $voters[(int) ($i['voter_id'] ?? 0)] ?? null;
            if (!in_array($type, ['mark', 'unmark'], true) || !$v || !$this->user()->allowsVoter($v)) { $rejected[] = ['uid' => $uid, 'why' => 'voter']; continue; }
            if (!$canPoll) { $rejected[] = ['uid' => $uid, 'why' => 'permission']; continue; }
            if (!$e || (int) ($i['election_id'] ?? 0) !== $e->id) { $rejected[] = ['uid' => $uid, 'why' => 'election']; continue; }
            // the phone's clock, unless it is clearly wrong (future, or older than 2 days)
            $at = null;
            try { $at = \Carbon\Carbon::parse((string) ($i['at'] ?? '')); } catch (\Throwable) {}
            if (!$at || $at->gt(now()->addMinutes(5)) || $at->lt(now()->subDays(2))) $at = now();
            $at = $at->setTimezone(config('app.timezone'));
            $m = PollMark::where('election_id', $e->id)->where('voter_id', $v->id)->first();
            if ($type === 'mark' && !$m) {
                try {
                    PollMark::create(['election_id' => $e->id, 'voter_id' => $v->id, 'assembly_no' => $v->assembly_no, 'booth_no' => $v->booth_no,
                        'marked_by' => $this->user()->username(), 'marked_at' => $at]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // another agent's phone marked the same voter a moment earlier: already counted
                }
            } elseif ($type === 'unmark' && $m && $m->marked_at->lte($at)) {
                $m->delete();
            }
            $done[] = $uid;
            $per[$v->assembly_no . '|' . $v->booth_no] = ($per[$v->assembly_no . '|' . $v->booth_no] ?? 0) + 1;
        }
        // which booths the phone holds: report sync time and send back their current marks
        $marks = [];
        foreach (array_slice((array) $r->input('booths', []), 0, 20) as $bk) {
            $a = (int) ($bk['a'] ?? 0); $b = (int) ($bk['b'] ?? 0);
            if (!$a || !$b || !$this->user()->allows($a)) continue;
            $this->touch($a, $b, 'synced_at', $per[$a . '|' . $b] ?? 0);
            if ($e) $marks[$a . '|' . $b] = PollMark::where('election_id', $e->id)->where('assembly_no', $a)->where('booth_no', $b)->pluck('voter_id')->all();
        }
        if ($done) $this->audit()->log('UPDATE', 'POLL', null, 'offline poll-day sync: ' . count($done) . ' marks / unmarks');
        return response()->json(['done' => $done, 'rejected' => $rejected, 'marks' => (object) $marks, 'at' => now()->toIso8601String()]);
    }
}
