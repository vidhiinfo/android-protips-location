<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\Voter;
use App\Models\Worker;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Poll day > Transport: voters aged 85+, with a disability or ill who need a vehicle / wheelchair / escort to reach the booth.
 * Requests move REQUESTED -> ASSIGNED (vehicle + driver) -> PICKED -> VOTED (also marks the voter as voted) or CANCELLED.
 */
class TransportController extends Controller
{
    public const REASONS = ['AGE_85', 'DISABLED', 'ILL', 'OTHER'];
    public const NEEDS = ['VEHICLE', 'WHEELCHAIR', 'ESCORT'];
    public const STATUSES = ['REQUESTED', 'ASSIGNED', 'PICKED', 'VOTED', 'CANCELLED'];

    public function __construct(private VoterService $voters) {}

    private function election(): ?int { return Election::where('current', true)->value('id'); }

    private function scope($q)
    {
        $ab = \App\Support\Area::current()['booths'] ?? [];
        return $q->when($ab, fn ($x) => $x->whereIn('booth_no', $ab));
    }

    public function index(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $st = in_array($r->query('status'), self::STATUSES, true) ? $r->query('status') : null;
        $eid = $this->election();
        $base = fn () => $this->scope(DB::table('transport_requests')->where('assembly_no', $a)->where(fn ($q) => $eid ? $q->where('election_id', $eid) : $q->whereNull('election_id')))
            ->when($b, fn ($q) => $q->where('booth_no', $b));
        $rows = $base()->when($st, fn ($q) => $q->where('status', $st))->orderByRaw("case status when 'REQUESTED' then 0 when 'ASSIGNED' then 1 when 'PICKED' then 2 when 'VOTED' then 3 else 4 end")
            ->orderByRaw('pickup_time is null')->orderBy('pickup_time')->orderBy('booth_no')->limit(1000)->get();
        $counts = $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $voters = Voter::whereIn('id', $rows->pluck('voter_id')->all() ?: [0])->get()->keyBy('id');
        // voters aged 85 or more without a request yet (one booth at a time)
        $eligible = $b ? Voter::where('assembly_no', $a)->where('booth_no', $b)->where('deleted', false)->where('age', '>=', 85)
            ->whereNotIn('id', $base()->pluck('voter_id')->all() ?: [0])->orderBy('serial_no')->limit(200)->get() : collect();
        return view('transport.index', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'booths' => $a ? $this->voters->booths($a) : [], 'boothNo' => $b,
            'status' => $st, 'rows' => $rows, 'voters' => $voters, 'counts' => $counts, 'eligible' => $eligible, 'hasElection' => (bool) $eid,
            'workers' => Worker::where('assembly_no', $a)->where('active', true)->orderBy('name')->get()->keyBy('id'),
            'prefill' => $r->query('voterId') ? Voter::find((int) $r->query('voterId')) : null]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['voter_ids' => 'required|array|max:200', 'voter_ids.*' => 'integer', 'reason' => 'required|in:' . implode(',', self::REASONS),
            'need' => 'required|in:' . implode(',', self::NEEDS), 'pickup_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'], 'notes' => 'nullable|string|max:500',
            'mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/']], ['mobile.regex' => t('validation.mobile')]);
        $eid = $this->election();
        $n = 0;
        foreach (Voter::whereIn('id', $d['voter_ids'])->get() as $v) {
            if (!$this->user()->allowsVoter($v) || $v->deleted) continue;
            $exists = DB::table('transport_requests')->where('voter_id', $v->id)->where(fn ($q) => $eid ? $q->where('election_id', $eid) : $q->whereNull('election_id'))->exists();
            if ($exists) continue;
            DB::table('transport_requests')->insert(['voter_id' => $v->id, 'election_id' => $eid, 'assembly_no' => $v->assembly_no, 'booth_no' => $v->booth_no,
                'reason' => $d['reason'], 'need' => $d['need'], 'pickup_address' => mb_substr(trim($v->house_no . ' ' . $v->fullAddress()), 0, 300),
                'pickup_time' => $d['pickup_time'] ?? null, 'mobile' => ($d['mobile'] ?? null) ?: $v->mobile, 'status' => 'REQUESTED', 'worker_id' => $v->worker_id,
                'notes' => $d['notes'] ?? null, 'created_by' => $this->user()->username(), 'created_at' => now(), 'updated_at' => now()]);
            $n++;
        }
        $this->audit()->log('CREATE', 'TRANSPORT', null, "$n transport request(s)");
        $first = Voter::find($d['voter_ids'][0] ?? 0);
        return redirect('/transport?' . http_build_query(array_filter(['assemblyNo' => $first?->assembly_no, 'boothNo' => $r->input('boothNo')])))
            ->with($n ? 'success' : 'error', $n ? t('tr.added', $n) : t('tr.already'));
    }

    public function update(Request $r, int $id)
    {
        $t = DB::table('transport_requests')->find($id);
        abort_if(!$t || !$this->user()->allows($t->assembly_no) || (($ab = \App\Support\Area::current()['booths'] ?? []) && !in_array((int) $t->booth_no, $ab, true)), 404, t('error.notFound'));
        $d = $r->validate(['status' => 'nullable|in:' . implode(',', self::STATUSES), 'vehicle' => 'nullable|string|max:60', 'driver_name' => 'nullable|string|max:80',
            'driver_mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'], 'pickup_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'], 'worker_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500'], ['driver_mobile.regex' => t('validation.mobile')]);
        $upd = array_filter(array_intersect_key($d, array_flip(['vehicle', 'driver_name', 'driver_mobile', 'pickup_time', 'notes', 'worker_id'])), fn ($v) => $v !== null);
        if ($r->has('vehicle') && !isset($upd['vehicle'])) $upd['vehicle'] = null;
        $status = $d['status'] ?? null;
        if (!$status && !empty($upd['vehicle']) && $t->status === 'REQUESTED') $status = 'ASSIGNED';   // a vehicle given = assigned
        if ($status) $upd['status'] = $status;
        $upd['updated_at'] = now();
        DB::table('transport_requests')->where('id', $t->id)->update($upd);
        // voted: also the poll-day "voted" mark, so turnout counts it
        if ($status === 'VOTED' && $t->election_id && \Illuminate\Support\Facades\Schema::hasTable('poll_marks')
            && !DB::table('poll_marks')->where('election_id', $t->election_id)->where('voter_id', $t->voter_id)->exists()) {
            DB::table('poll_marks')->insert(['election_id' => $t->election_id, 'voter_id' => $t->voter_id, 'assembly_no' => $t->assembly_no, 'booth_no' => $t->booth_no,
                'marked_by' => $this->user()->username(), 'marked_at' => now()]);
        }
        $this->audit()->log('UPDATE', 'TRANSPORT', $t->id, $status ? 'status ' . $status : 'details');
        return back()->with('success', t('tr.saved'));
    }

    /** Printable list for drivers / booth teams. */
    public function print(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $eid = $this->election();
        $rows = $this->scope(DB::table('transport_requests')->where('assembly_no', $a)->where(fn ($q) => $eid ? $q->where('election_id', $eid) : $q->whereNull('election_id')))
            ->when($b, fn ($q) => $q->where('booth_no', $b))->whereNotIn('status', ['CANCELLED', 'VOTED'])
            ->orderBy('vehicle')->orderByRaw('pickup_time is null')->orderBy('pickup_time')->orderBy('booth_no')->get();
        return view('transport.print', ['rows' => $rows, 'voters' => Voter::whereIn('id', $rows->pluck('voter_id')->all() ?: [0])->get()->keyBy('id'),
            'assemblyNo' => $a, 'boothNo' => $b]);
    }
}
