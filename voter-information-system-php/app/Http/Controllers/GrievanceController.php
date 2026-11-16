<?php

namespace App\Http\Controllers;

use App\Models\Grievance;
use App\Models\Voter;
use App\Models\Worker;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;

/** Voter grievances / complaints: logged, assigned to a worker, followed up to resolution. */
class GrievanceController extends Controller
{
    public function __construct(private VoterService $voters) {}

    private function find(int $id): Grievance
    {
        $g = Grievance::find($id);
        abort_if(!$g || !$this->user()->allows($g->assembly_no), 404, t('error.notFound'));
        return $g;
    }

    public function index(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo'));
        $f = ['status' => in_array($r->query('status'), array_merge(Grievance::STATUSES, ['ACTIVE', 'ALL']), true) ? $r->query('status') : 'ACTIVE',
            'category' => in_array($r->query('category'), Grievance::CATEGORIES, true) ? $r->query('category') : null,
            'boothNo' => $r->query('boothNo') ? (int) $r->query('boothNo') : null, 'workerId' => $r->query('workerId') ? (int) $r->query('workerId') : null,
            'q' => trim((string) $r->query('q')), 'voterId' => $r->query('voterId') ? (int) $r->query('voterId') : null];
        $q = Grievance::with(['worker', 'voter'])->when($a, fn ($q) => $q->where('assembly_no', $a))
            ->when($f['status'] === 'ACTIVE', fn ($q) => $q->whereIn('status', ['OPEN', 'IN_PROGRESS']), fn ($q) => $q->when($f['status'] !== 'ALL', fn ($q) => $q->where('status', $f['status'])))
            ->when($f['voterId'], fn ($q, $v) => $q->where('voter_id', $v))
            ->when($f['category'], fn ($q, $c) => $q->where('category', $c))->when($f['boothNo'], fn ($q, $b) => $q->where('booth_no', $b))
            ->when($f['workerId'], fn ($q, $w) => $q->where('worker_id', $w))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . $f['q'] . '%')->orWhere('subject', 'like', '%' . $f['q'] . '%')
                ->orWhere('mobile', 'like', '%' . $f['q'] . '%')->orWhere('id', (int) $f['q'])))
            ->orderByRaw("case priority when 'URGENT' then 0 when 'HIGH' then 1 when 'NORMAL' then 2 else 3 end")->orderByDesc('created_at');
        $counts = Grievance::when($a, fn ($q) => $q->where('assembly_no', $a))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        return view('grievances.index', ['page' => $q->paginate(50)->withQueryString(), 'f' => $f, 'counts' => $counts, 'assemblyNo' => $a,
            'booths' => $this->voters->booths($a), 'workers' => Worker::where('active', true)->when($a, fn ($q) => $q->where('assembly_no', $a))->orderBy('name')->get()]);
    }

    public function form(Request $r, ?int $id = null)
    {
        if ($id) $g = $this->find($id);
        else {
            $v = $r->query('voterId') ? Voter::find((int) $r->query('voterId')) : null;
            if ($v && !$this->user()->allowsVoter($v)) $v = null;
            $g = new Grievance(['status' => 'OPEN', 'priority' => 'NORMAL', 'voter_id' => $v?->id, 'name' => $v?->name, 'mobile' => $v?->mobile,
                'assembly_no' => $v?->assembly_no ?? $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($this->voters->assemblies()),
                'ward_no' => $v?->ward_no, 'booth_no' => $v?->booth_no, 'location' => $v ? trim($v->house_no . ' ' . $v->fullAddress()) : null,
                'worker_id' => $v?->worker_id]);
        }
        return view('grievances.form', ['g' => $g, 'assemblies' => $this->voters->assemblies(), 'booths' => $this->voters->booths($g->assembly_no),
            'workers' => Worker::where('active', true)->where('assembly_no', $g->assembly_no)->orderBy('name')->get()]);
    }

    public function save(Request $r, ?int $id = null)
    {
        $d = $r->validate([
            'name' => ['required', 'max:120'], 'mobile' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'], 'assembly_no' => ['required', 'integer', 'min:1'],
            'booth_no' => ['nullable', 'integer', 'min:1'], 'category' => ['required', 'in:' . implode(',', Grievance::CATEGORIES)],
            'subject' => ['required', 'max:200'], 'description' => ['nullable', 'max:2000'], 'location' => ['nullable', 'max:300'],
            'priority' => ['required', 'in:' . implode(',', Grievance::PRIORITIES)], 'status' => ['required', 'in:' . implode(',', Grievance::STATUSES)],
            'worker_id' => ['nullable', 'integer'], 'resolution' => ['nullable', 'max:1000'], 'voter_id' => ['nullable', 'integer'],
        ], ['mobile.regex' => t('validation.mobile'), 'required' => t('validation.required')]);
        foreach (['mobile', 'booth_no', 'description', 'location', 'worker_id', 'resolution', 'voter_id'] as $k) $d[$k] = $d[$k] ?? null;
        $d['assembly_no'] = $this->user()->restrict($d['assembly_no']);
        if ($d['worker_id'] && !Worker::where('id', $d['worker_id'])->where('assembly_no', $d['assembly_no'])->exists()) $d['worker_id'] = null;
        $g = $id ? $this->find($id) : new Grievance(['created_by' => $this->user()->username()]);
        if ($id) unset($d['voter_id']);
        elseif ($d['voter_id'] && ($v = Voter::find($d['voter_id'])) && $this->user()->allowsVoter($v)) $d['ward_no'] = $v->ward_no;
        else $d['voter_id'] = null;
        $wasOpen = !$g->exists || $g->isOpen();
        $g->fill($d);
        if ($wasOpen && !$g->isOpen()) $g->resolved_at = now();
        if ($g->isOpen()) $g->resolved_at = null;
        $g->save();
        $this->audit()->log($id ? 'UPDATE' : 'CREATE', 'GRIEVANCE', $g->id, $g->status . ' ' . $g->category . ': ' . $g->subject);
        return redirect('/grievances/' . $g->id)->with('success', 'common.saved');
    }

    public function show(int $id)
    {
        $g = $this->find($id);
        $others = $g->voter_id ? Grievance::where('voter_id', $g->voter_id)->where('id', '<>', $g->id)->orderByDesc('created_at')->get() : collect();
        return view('grievances.show', ['g' => $g, 'others' => $others]);
    }

    /** Quick status change from the list or the grievance page. */
    public function status(Request $r, int $id)
    {
        $g = $this->find($id);
        $s = $r->input('status');
        abort_unless(in_array($s, Grievance::STATUSES, true), 422);
        $wasOpen = $g->isOpen();
        $g->status = $s;
        if ($r->filled('resolution')) $g->resolution = mb_substr((string) $r->input('resolution'), 0, 1000);
        if ($wasOpen && !$g->isOpen()) $g->resolved_at = now();
        if ($g->isOpen()) $g->resolved_at = null;
        $g->save();
        $this->audit()->log('UPDATE', 'GRIEVANCE', $g->id, 'status ' . $s);
        return back()->with('success', 'common.saved');
    }
}
