<?php

namespace App\Http\Controllers;

use App\Models\Voter;
use App\Services\Messaging\MessagingService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phone bank: callers get one voter at a time (never the same voter as another caller), ring, and record the outcome.
 * No answer / busy / switched off come back after CALLBACK_HOURS; "call back" at the time the voter asked for.
 */
class PhoneBankController extends Controller
{
    public const OUTCOMES = ['ANSWERED', 'NO_ANSWER', 'BUSY', 'SWITCHED_OFF', 'CALL_BACK', 'WRONG_NUMBER', 'REFUSED'];
    private const RETRY = ['NO_ANSWER', 'BUSY', 'SWITCHED_OFF'];
    private const CALLBACK_HOURS = 3;
    private const CLAIM_SECONDS = 900;

    public function __construct(private VoterService $voters, private MessagingService $msg) {}

    private function pick(Request $r): array
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $mode = in_array($r->query('mode'), ['new', 'callback'], true) ? $r->query('mode') : 'new';
        return [$a, $b, $mode];
    }

    /** Voters this caller may ring next, in roll order. */
    private function candidates(int $a, ?int $b, string $mode)
    {
        $q = Voter::where('assembly_no', $a)->where('deleted', false)->whereNotNull('mobile')->where('mobile', '<>', '')
            ->when($b, fn ($x) => $x->where('booth_no', $b))
            ->when($this->user()->workerId(), fn ($x, $w) => $x->where('worker_id', $w))
            ->when(\App\Services\Privacy\PrivacyService::enabled(), fn ($x) => $x->where(fn ($y) => $y->whereNull('consent_contact')->orWhere('consent_contact', '<>', 'N')))
            ->whereNotIn('mobile', DB::table('message_optouts')->whereNull('channel')->select('mobile'));
        if ($mode === 'new') {
            $q->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('call_logs')->whereColumn('call_logs.voter_id', 'voters.id'));
        } else {
            $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('call_logs as c')->whereColumn('c.voter_id', 'voters.id')
                ->whereRaw('c.id = (select max(c2.id) from call_logs c2 where c2.voter_id = voters.id)')
                ->whereIn('c.outcome', array_merge(self::RETRY, ['CALL_BACK']))->where('c.callback_at', '<=', now()));
        }
        return $q->orderBy('booth_no')->orderBy('serial_no')->orderBy('id');
    }

    public function index(Request $r)
    {
        [$a, $b, $mode] = $this->pick($r);
        $me = $this->user()->username();
        $voter = null;
        if ($a) {
            // the voter this caller already holds, else the first one nobody else holds
            $held = Cache::get('call.held.' . $me);
            if ($held && ($v = Voter::find($held)) && $v->assembly_no === $a && (!$b || $v->booth_no === $b) && Cache::get('call.claim.' . $v->id) === $me) $voter = $v;
            if (!$voter) {
                foreach ($this->candidates($a, $b, $mode)->limit(40)->get() as $v) {
                    if (Cache::add('call.claim.' . $v->id, $me, self::CLAIM_SECONDS)) { $voter = $v; Cache::put('call.held.' . $me, $v->id, self::CLAIM_SECONDS); break; }
                }
            }
        }
        $today = now()->startOfDay();
        $stats = DB::table('call_logs')->where('assembly_no', $a)->where('at', '>=', $today)->selectRaw('outcome, count(*) as n')->groupBy('outcome')->pluck('n', 'outcome')->all();
        $mine = DB::table('call_logs')->where('by_user', $me)->where('at', '>=', $today)->count();
        $callers = DB::table('call_logs')->where('assembly_no', $a)->where('at', '>=', $today)->selectRaw('by_user, count(*) as n')->groupBy('by_user')->orderByDesc('n')->limit(10)->pluck('n', 'by_user');
        return view('phonebank.index', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'booths' => $a ? $this->voters->booths($a) : [], 'boothNo' => $b,
            'mode' => $mode, 'voter' => $voter, 'history' => $voter ? self::history($voter->id) : collect(), 'stats' => $stats, 'mine' => $mine, 'callers' => $callers,
            'left' => $a ? $this->candidates($a, $b, $mode)->count() : 0, 'quick' => \App\Support\StatusMasters::quickLeanings(),
            'tel' => $voter ? $this->msg->e164($voter->mobile) : null]);
    }

    public static function history(int $voterId)
    {
        return DB::table('call_logs')->where('voter_id', $voterId)->orderByDesc('id')->limit(20)->get();
    }

    public function save(Request $r, int $id)
    {
        $v = Voter::find($id);
        abort_if(!$v || !$this->user()->allowsVoter($v), 404, t('error.notFound'));
        $d = $r->validate(['outcome' => 'required|in:' . implode(',', self::OUTCOMES), 'leaning' => 'nullable|in:S,N,O', 'notes' => 'nullable|string|max:500',
            'callback_at' => 'nullable|date', 'set_status' => 'nullable|boolean', 'remove_number' => 'nullable|boolean']);
        $callback = match (true) {
            $d['outcome'] === 'CALL_BACK' => !empty($d['callback_at']) ? \Carbon\Carbon::parse($d['callback_at']) : now()->addDay(),
            in_array($d['outcome'], self::RETRY, true) => now()->addHours(self::CALLBACK_HOURS),
            default => null,
        };
        $leaning = $d['outcome'] === 'ANSWERED' ? ($d['leaning'] ?? null) : null;
        DB::table('call_logs')->insert(['voter_id' => $v->id, 'assembly_no' => $v->assembly_no, 'booth_no' => $v->booth_no, 'outcome' => $d['outcome'],
            'leaning' => $leaning, 'notes' => $d['notes'] ?? null, 'callback_at' => $callback, 'by_user' => $this->user()->username(), 'at' => now()]);
        $changes = [];
        if ($leaning && $r->boolean('set_status') && ($status = \App\Support\StatusMasters::quickLeanings()[$leaning] ?? null) && $v->political_status !== $status) {
            $changes[] = "political status {$v->political_status} -> $status";
            $v->political_status = $status;
        }
        if ($d['outcome'] === 'WRONG_NUMBER' && $r->boolean('remove_number') && $v->mobile) {
            $changes[] = 'wrong mobile removed';
            $v->mobile = null;
        }
        if ($changes) $v->save();
        $this->audit()->log('UPDATE', 'VOTER', $v->id, 'phone bank: ' . $d['outcome'] . ($changes ? '; ' . implode('; ', $changes) : ''));
        Cache::forget('call.claim.' . $v->id);
        Cache::forget('call.held.' . $this->user()->username());
        return redirect('/phonebank?' . http_build_query(array_filter(['assemblyNo' => $v->assembly_no, 'boothNo' => $r->input('boothNo'), 'mode' => $r->input('mode')])))
            ->with('success', t('pb.saved', $v->name, t('pb.o.' . $d['outcome'])));
    }

    /** Let go of the current voter without a call (e.g. caller stops). */
    public function skip(Request $r, int $id)
    {
        if (Cache::get('call.claim.' . $id) === $this->user()->username()) Cache::forget('call.claim.' . $id);
        Cache::forget('call.held.' . $this->user()->username());
        // skipped voters are not offered to this caller again for the claim period
        Cache::put('call.claim.' . $id, '-skip-', 300);
        return redirect('/phonebank?' . http_build_query(array_filter(['assemblyNo' => $r->input('assemblyNo'), 'boothNo' => $r->input('boothNo'), 'mode' => $r->input('mode')])));
    }
}
