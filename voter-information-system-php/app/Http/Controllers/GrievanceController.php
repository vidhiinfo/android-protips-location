<?php

namespace App\Http\Controllers;

use App\Models\Grievance;
use App\Models\Voter;
use App\Models\Worker;
use App\Services\Grievances\GrievanceService;
use App\Services\Voters\VoterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/** Voter grievances / complaints: logged, assigned to a worker, followed up to resolution. */
class GrievanceController extends Controller
{
    public function __construct(private VoterService $voters, private GrievanceService $svc) {}

    private static function hasSla(): bool { return \Illuminate\Support\Facades\Schema::hasColumn('grievances', 'due_at'); }

    /** Grievances inside the user's access area: its wards / booths; with a serial range, its voters or what the user logged. */
    public static function areaScope($q)
    {
        $a = \App\Support\Area::current();
        if (!$a) return $q;
        if ($a['wards']) $q->whereIn('ward_no', $a['wards']);
        if ($a['booths']) $q->whereIn('booth_no', $a['booths']);
        if ($a['from'] !== null || $a['to'] !== null) {
            $ids = Voter::pluck('id')->all();   // the area's voters (Voter global scope)
            $q->where(fn ($w) => $w->whereIn('voter_id', $ids ?: [0])->orWhere('created_by', app(\App\Services\Auth\CurrentUser::class)->username()));
        }
        return $q;
    }

    private function find(int $id): Grievance
    {
        $g = self::areaScope(Grievance::query())->find($id);
        abort_if(!$g || !$this->user()->allows($g->assembly_no), 404, t('error.notFound'));
        return $g;
    }

    public function index(Request $r)
    {
        $a = $this->user()->restrict($r->query('assemblyNo'));
        $f = ['status' => in_array($r->query('status'), array_merge(Grievance::STATUSES, ['ACTIVE', 'OVERDUE', 'ALL']), true) ? $r->query('status') : 'ACTIVE',
            'category' => in_array($r->query('category'), Grievance::CATEGORIES, true) ? $r->query('category') : null,
            'boothNo' => $r->query('boothNo') ? (int) $r->query('boothNo') : null, 'workerId' => $r->query('workerId') ? (int) $r->query('workerId') : null,
            'q' => trim((string) $r->query('q')), 'voterId' => $r->query('voterId') ? (int) $r->query('voterId') : null];
        $q = self::areaScope(Grievance::with(['worker', 'voter']))->when($a, fn ($q) => $q->where('assembly_no', $a))
            ->when(in_array($f['status'], ['ACTIVE', 'OVERDUE'], true), fn ($q) => $q->whereIn('status', ['OPEN', 'IN_PROGRESS']), fn ($q) => $q->when($f['status'] !== 'ALL', fn ($q) => $q->where('status', $f['status'])))
            ->when($f['status'] === 'OVERDUE', fn ($q) => $q->whereNotNull('due_at')->where('due_at', '<', now()))
            ->when($f['voterId'], fn ($q, $v) => $q->where('voter_id', $v))
            ->when($f['category'], fn ($q, $c) => $q->where('category', $c))->when($f['boothNo'], fn ($q, $b) => $q->where('booth_no', $b))
            ->when($f['workerId'], fn ($q, $w) => $q->where('worker_id', $w))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . $f['q'] . '%')->orWhere('subject', 'like', '%' . $f['q'] . '%')
                ->orWhere('mobile', 'like', '%' . $f['q'] . '%')->orWhere('id', (int) $f['q'])))
            ->orderByRaw("case priority when 'URGENT' then 0 when 'HIGH' then 1 when 'NORMAL' then 2 else 3 end")->orderByDesc('created_at');
        $counts = self::areaScope(Grievance::query())->when($a, fn ($q) => $q->where('assembly_no', $a))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $overdue = self::hasSla() ? self::areaScope(Grievance::query())->when($a, fn ($q) => $q->where('assembly_no', $a))->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->whereNotNull('due_at')->where('due_at', '<', now())->count() : 0;
        return view('grievances.index', ['page' => $q->paginate(50)->withQueryString(), 'f' => $f, 'counts' => $counts, 'assemblyNo' => $a, 'overdue' => $overdue,
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
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ], ['mobile.regex' => t('validation.mobile'), 'required' => t('validation.required')]);
        foreach (['mobile', 'booth_no', 'description', 'location', 'worker_id', 'resolution', 'voter_id'] as $k) $d[$k] = $d[$k] ?? null;
        $d['assembly_no'] = $this->user()->restrict($d['assembly_no']);
        if (($ar = \App\Support\Area::current()) && $ar['booths'] && !in_array((int) $d['booth_no'], $ar['booths'], true)) $d['booth_no'] = $ar['booths'][0];   // keep it inside the area
        if ($d['worker_id'] && !Worker::where('id', $d['worker_id'])->where('assembly_no', $d['assembly_no'])->exists()) $d['worker_id'] = null;
        $g = $id ? $this->find($id) : new Grievance(['created_by' => $this->user()->username()]);
        if ($id) unset($d['voter_id']);
        elseif ($d['voter_id'] && ($v = Voter::find($d['voter_id'])) && $this->user()->allowsVoter($v)) $d['ward_no'] = $v->ward_no;
        else $d['voter_id'] = null;
        $wasOpen = !$g->exists || $g->isOpen();
        $oldStatus = $g->status;
        $due = $d['due_date'] ?? null; unset($d['due_date']);
        $g->fill($d);
        if ($wasOpen && !$g->isOpen()) $g->resolved_at = now();
        if ($g->isOpen()) $g->resolved_at = null;
        if (self::hasSla()) {
            // deadline: typed date, else days for the category / priority from when it was logged
            $auto = GrievanceService::dueAt($g->category, $g->priority, $g->created_at ?? now());
            $newDue = $due ? \Carbon\Carbon::parse($due)->endOfDay() : ($g->exists && !$g->isDirty(['category', 'priority']) && $g->due_at ? $g->due_at : $auto);
            if (!$g->due_at || $newDue->ne($g->due_at)) { $g->due_at = $newDue; if ($newDue->isFuture()) $g->escalation_level = 0; }
        }
        $g->save();
        if ($id && $oldStatus !== $g->status) $this->svc->note($g->id, 'STATUS', t('gr.status.' . $g->status), $this->user()->username());
        if (!$id) $this->svc->note($g->id, 'STATUS', t('grv.logged'), $this->user()->username());
        $this->audit()->log($id ? 'UPDATE' : 'CREATE', 'GRIEVANCE', $g->id, $g->status . ' ' . $g->category . ': ' . $g->subject);
        return redirect('/grievances/' . $g->id)->with('success', 'common.saved');
    }

    public function show(int $id)
    {
        $g = $this->find($id);
        $others = $g->voter_id ? Grievance::where('voter_id', $g->voter_id)->where('id', '<>', $g->id)->orderByDesc('created_at')->get() : collect();
        $sla = GrievanceService::enabled();
        return view('grievances.show', ['g' => $g, 'others' => $others, 'sla' => $sla,
            'updates' => $sla ? DB::table('grievance_updates')->where('grievance_id', $g->id)->orderBy('id')->get() : collect(),
            'photos' => $sla ? DB::table('grievance_photos')->where('grievance_id', $g->id)->orderBy('id')->get() : collect()]);
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
        $this->svc->note($g->id, 'STATUS', t('gr.status.' . $s) . ($r->filled('resolution') ? ': ' . $r->input('resolution') : ''), $this->user()->username());
        $this->audit()->log('UPDATE', 'GRIEVANCE', $g->id, 'status ' . $s);
        return back()->with('success', 'common.saved');
    }

    // ---------------------------------------------------------------- timeline and photos

    public function note(Request $r, int $id)
    {
        $g = $this->find($id);
        $text = trim((string) $r->input('text'));
        if ($text === '') return back()->with('error', t('grv.noteEmpty'));
        $this->svc->note($g->id, 'NOTE', $text, $this->user()->username());
        $g->touch();
        return back()->with('success', t('grv.noteSaved'));
    }

    public function upload(Request $r, int $id)
    {
        $g = $this->find($id);
        $r->validate(['photos' => 'required|array|max:5', 'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:' . GrievanceService::MAX_KB,
            'stage' => 'nullable|in:BEFORE,AFTER', 'caption' => 'nullable|string|max:200'], ['photos.*.mimes' => t('grv.photoType'), 'photos.*.max' => t('grv.photoSize')]);
        $have = DB::table('grievance_photos')->where('grievance_id', $g->id)->count();
        $n = 0;
        foreach ($r->file('photos') as $f) {
            if ($have + $n >= GrievanceService::MAX_PHOTOS) break;
            try { $this->svc->addPhoto($g, $f, (string) $r->input('stage', 'BEFORE'), $r->input('caption'), $this->user()->username()); $n++; }
            catch (\InvalidArgumentException) { return back()->with('error', t('grv.photoType')); }
        }
        $this->audit()->log('UPDATE', 'GRIEVANCE', $g->id, "$n photo(s) added");
        return back()->with($n ? 'success' : 'error', $n ? t('grv.photosAdded', $n) : t('grv.photoLimit', GrievanceService::MAX_PHOTOS));
    }

    private function photo(int $id, int $pid): object
    {
        $g = $this->find($id);
        $p = DB::table('grievance_photos')->where('grievance_id', $g->id)->find($pid);
        abort_if(!$p || !is_file(GrievanceService::photoPath($p)), 404, t('error.notFound'));
        return $p;
    }

    public function showPhoto(int $id, int $pid)
    {
        $p = $this->photo($id, $pid);
        return response()->file(GrievanceService::photoPath($p), ['Content-Type' => $p->mime, 'Cache-Control' => 'private, max-age=86400']);
    }

    public function deletePhoto(int $id, int $pid)
    {
        $p = $this->photo($id, $pid);
        $this->svc->deletePhoto($p);
        $this->svc->note($id, 'PHOTO', t('grv.photoDeleted'), $this->user()->username());
        $this->audit()->log('UPDATE', 'GRIEVANCE', $id, 'photo deleted');
        return back()->with('success', t('grv.photoDeleted'));
    }

    // ---------------------------------------------------------------- deadlines (SLA) per category

    public function deadlines()
    {
        $users = DB::table('app_users')->where('enabled', true)->orderBy('username')->pluck('username');
        return view('grievances.deadlines', ['sla' => GrievanceService::sla(), 'users' => $users,
            'alerts' => in_array(config('voters.grievance.alert_channel'), ['sms', 'whatsapp'], true)]);
    }

    public function saveDeadlines(Request $r)
    {
        $users = DB::table('app_users')->pluck('username')->flip();
        foreach (Grievance::CATEGORIES as $c) {
            $days = (int) $r->input("days.$c");
            if ($days < 1 || $days > 365) continue;
            $to = $r->input("to.$c"); $to2 = $r->input("to2.$c");
            DB::table('grievance_sla')->updateOrInsert(['category' => $c], ['days' => $days,
                'escalate_to' => $to && isset($users[$to]) ? $to : null, 'escalate_to2' => $to2 && isset($users[$to2]) ? $to2 : null]);
        }
        $this->audit()->log('UPDATE', 'GRIEVANCE', null, 'deadlines (SLA) changed');
        return redirect('/grievances/deadlines')->with('success', t('grv.deadlinesSaved'));
    }
}
