<?php

namespace App\Http\Controllers;

use App\Models\AppUser;
use App\Models\Election;
use App\Models\Voter;
use App\Models\Worker;
use App\Services\Voters\VoterService;
use App\Support\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Party workers: who is responsible for which voters, and how far each one has got (leaderboard). */
class WorkerController extends Controller
{
    public const PANNA_SIZE = 30;

    public function __construct(private VoterService $voters) {}

    private function find(int $id): Worker
    {
        $w = Worker::find($id);
        abort_if(!$w || !$this->user()->allows($w->assembly_no), 404, t('error.notFound'));
        return $w;
    }

    /** Per worker: voters, houses, surveyed, supporters, with mobile, voted today. */
    public function stats(array $ids): array
    {
        if (!$ids) return [];
        $eid = (int) Election::where('current', true)->value('id');
        $out = [];
        foreach (Voter::whereIn('worker_id', $ids)->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))
                     ->selectRaw("worker_id, count(*) as n,
                        sum(case when (political_status is not null and political_status <> '') or (caste is not null and caste <> '') then 1 else 0 end) as surveyed,
                        sum(case when mobile is not null and mobile <> '' then 1 else 0 end) as mobile,
                        sum(case when " . \App\Services\Reports\AnalyticsService::SENTIMENT_SQL . " = 'S' then 1 else 0 end) as support,
                        sum(case when exists (select 1 from poll_marks p where p.voter_id = voters.id and p.election_id = ?) then 1 else 0 end) as voted", [$eid])
                     ->groupBy('worker_id')->get() as $r) {
            $out[$r->worker_id] = ['n' => (int) $r->n, 'surveyed' => (int) $r->surveyed, 'mobile' => (int) $r->mobile, 'support' => (int) $r->support, 'voted' => (int) $r->voted];
        }
        foreach (Voter::whereIn('worker_id', $ids)->whereNotNull('house_no')->where('house_no', '<>', '')->selectRaw('worker_id')
                     ->groupBy('worker_id', DB::raw(Text::HOUSE_KEY_SQL), DB::raw("lower(trim(coalesce(address, '')))"))->cursor() as $r) {
            $out[$r->worker_id]['houses'] = ($out[$r->worker_id]['houses'] ?? 0) + 1;
        }
        return $out;
    }

    public function index(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies());
        $b = $r->query('boothNo') ? (int) $r->query('boothNo') : null;
        $role = in_array($r->query('role'), Worker::ROLES, true) ? $r->query('role') : null;
        $workers = Worker::where('assembly_no', $a)->when($b, fn ($q) => $q->where('booth_no', $b))->when($role, fn ($q) => $q->where('role', $role))
            ->orderByDesc('active')->orderBy('booth_no')->orderBy('name')->get();
        $stats = $this->stats($workers->pluck('id')->all());
        $sorted = $workers->sortByDesc(fn ($w) => [$w->active ? 1 : 0, ($s = $stats[$w->id] ?? null) && $s['n'] ? $s['surveyed'] / $s['n'] : -1, $s['n'] ?? 0])->values();
        $unassigned = Voter::where('assembly_no', $a)->when($b, fn ($q) => $q->where('booth_no', $b))->whereNull('worker_id')
            ->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))->count();
        return view('workers.index', ['workers' => $sorted, 'stats' => $stats, 'assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a,
            'booths' => $this->voters->booths($a), 'boothNo' => $b, 'role' => $role, 'unassigned' => $unassigned]);
    }

    public function form(Request $r, ?int $id = null)
    {
        $w = $id ? $this->find($id) : new Worker(['assembly_no' => $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies()),
            'booth_no' => $r->query('boothNo'), 'role' => 'VOLUNTEER', 'active' => true]);
        return view('workers.form', ['w' => $w, 'assemblies' => $this->voters->assemblies(), 'booths' => $this->voters->booths($w->assembly_no),
            'usernames' => AppUser::orderBy('username')->pluck('username')->all()]);
    }

    public function save(Request $r, ?int $id = null)
    {
        $data = $r->validate([
            'name' => ['required', 'max:120'], 'mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'], 'assembly_no' => ['required', 'integer', 'min:1'],
            'booth_no' => ['nullable', 'integer', 'min:1'], 'role' => ['required', 'in:' . implode(',', Worker::ROLES)], 'username' => ['nullable', 'max:50'],
            'notes' => ['nullable', 'max:300'],
        ], ['mobile.regex' => t('validation.mobile'), 'required' => t('validation.required')]);
        $data['assembly_no'] = $this->user()->restrict($data['assembly_no']);
        $data['active'] = $r->boolean('active');
        foreach (['mobile', 'booth_no', 'username', 'notes'] as $k) $data[$k] = $data[$k] ?? null;
        if ($data['username'] && Worker::where('username', $data['username'])->where('id', '<>', $id ?? 0)->exists()) {
            return back()->withInput()->with('error', 'worker.userTaken');
        }
        $w = $id ? $this->find($id) : new Worker(['created_at' => now()]);
        $w->fill($data)->save();
        $this->audit()->log($id ? 'UPDATE' : 'CREATE', 'WORKER', $w->id, $w->name);
        return redirect('/workers/' . $w->id)->with('success', 'common.saved');
    }

    public function show(int $id)
    {
        $w = $this->find($id);
        $s = $this->stats([$w->id])[$w->id] ?? ['n' => 0, 'surveyed' => 0, 'mobile' => 0, 'support' => 0, 'voted' => 0];
        $ranges = Voter::where('worker_id', $w->id)->selectRaw('booth_no, min(serial_no) as lo, max(serial_no) as hi, count(*) as n')
            ->groupBy('booth_no')->orderBy('booth_no')->get();
        return view('workers.show', ['w' => $w, 's' => $s, 'ranges' => $ranges, 'booths' => $this->voters->booths($w->assembly_no)]);
    }

    /** Assign the voters of a booth with serial no. from..to to this worker. */
    public function assign(Request $r, int $id)
    {
        $w = $this->find($id);
        $d = $r->validate(['booth_no' => ['required', 'integer', 'min:1'], 'from' => ['required', 'integer', 'min:1'], 'to' => ['required', 'integer', 'min:1']]);
        [$lo, $hi] = [min($d['from'], $d['to']), max($d['from'], $d['to'])];
        $n = Voter::where('assembly_no', $w->assembly_no)->where('booth_no', $d['booth_no'])->whereBetween('serial_no', [$lo, $hi])
            ->when(!$r->boolean('replace'), fn ($q) => $q->whereNull('worker_id'))->update(['worker_id' => $w->id]);
        $this->audit()->log('UPDATE', 'WORKER', $w->id, "assigned $n voters: booth {$d['booth_no']} serial $lo-$hi");
        return redirect('/workers/' . $w->id)->with('success', t('worker.assigned', $n));
    }

    public function unassign(int $id)
    {
        $w = $this->find($id);
        $n = Voter::where('worker_id', $w->id)->update(['worker_id' => null]);
        $this->audit()->log('UPDATE', 'WORKER', $w->id, "unassigned $n voters");
        return redirect('/workers/' . $w->id)->with('success', t('worker.unassigned', $n));
    }

    /**
     * Panna split: the booth's roll in serial order is divided among its panna pramukhs in equal consecutive
     * pieces, never splitting a house. Voters already assigned keep their worker unless "replace" is ticked.
     */
    public function panna(Request $r)
    {
        $a = $this->user()->restrict($r->input('assemblyNo'));
        $b = (int) $r->input('boothNo');
        $workers = Worker::where('assembly_no', $a)->where('booth_no', $b)->where('role', 'PANNA_PRAMUKH')->where('active', true)->orderBy('id')->get();
        $back = '/workers?' . http_build_query(['assemblyNo' => $a, 'boothNo' => $b]);
        if (!$a || !$b || $workers->isEmpty()) return redirect($back)->with('error', 'worker.noPanna');
        $vs = Voter::where('assembly_no', $a)->where('booth_no', $b)->when(!$r->boolean('replace'), fn ($q) => $q->whereNull('worker_id'))
            ->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))->orderByRaw('serial_no is null')->orderBy('serial_no')->get(['id', 'house_no', 'address']);
        if ($vs->isEmpty()) return redirect($back)->with('error', 'worker.nothingToSplit');
        $houses = [];
        foreach ($vs as $v) $houses[Text::houseKey($v->house_no) === '' ? '#' . $v->id : Text::houseKey($v->house_no) . '|' . mb_strtolower(trim((string) $v->address))][] = $v->id;
        $per = (int) ceil($vs->count() / $workers->count());
        $i = 0; $count = 0; $done = [];
        foreach ($houses as $ids) {
            if ($count >= $per && $i < $workers->count() - 1) { $i++; $count = 0; }
            $done[$workers[$i]->id] = array_merge($done[$workers[$i]->id] ?? [], $ids); $count += count($ids);
        }
        foreach ($done as $wid => $ids) foreach (array_chunk($ids, 500) as $chunk) Voter::whereIn('id', $chunk)->update(['worker_id' => $wid]);
        $this->audit()->log('UPDATE', 'WORKER', null, "panna split booth $b: {$vs->count()} voters among {$workers->count()} panna pramukhs");
        return redirect($back)->with('success', t('worker.split', $vs->count(), $workers->count()));
    }

    public function destroy(int $id)
    {
        $w = $this->find($id);
        Voter::where('worker_id', $w->id)->update(['worker_id' => null]);
        \App\Models\Grievance::where('worker_id', $w->id)->update(['worker_id' => null]);
        $w->delete();
        $this->audit()->log('DELETE', 'WORKER', $id, $w->name);
        return redirect('/workers?assemblyNo=' . $w->assembly_no)->with('success', 'common.deleted');
    }
}
