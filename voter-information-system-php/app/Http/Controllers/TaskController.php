<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Services\Voters\VoterService;
use App\Services\Workers\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Worker tasks and targets (given by a manager, done by the worker), and daily attendance.
 * Managers (WORKER_MANAGE): /workers/tasks, /workers/attendance. Every login: /my/tasks, /my/attendance.
 */
class TaskController extends Controller
{
    public function __construct(private VoterService $voters, private TaskService $tasks) {}

    private function area(Request $r): array
    {
        $a = $this->user()->restrict($r->query('assemblyNo', $r->input('assembly_no'))) ?? array_key_first($this->voters->assemblies());
        return [$a, \App\Support\Area::current()['booths'] ?? []];
    }

    private function task(int $id): object
    {
        $t = DB::table('worker_tasks')->find($id);
        $ab = \App\Support\Area::current()['booths'] ?? [];
        abort_if(!$t || !$this->user()->allows($t->assembly_no) || ($ab && $t->booth_no && !in_array((int) $t->booth_no, $ab, true)), 404, t('error.notFound'));
        return $t;
    }

    // ---------------------------------------------------------------- manager

    public function index(Request $r)
    {
        [$a, $ab] = $this->area($r);
        $status = in_array($r->query('status'), ['OPEN', 'DONE', 'ALL'], true) ? $r->query('status') : 'OPEN';
        $wid = $r->query('workerId') ? (int) $r->query('workerId') : null;
        $list = DB::table('worker_tasks')->where('assembly_no', $a)->when($ab, fn ($q) => $q->where(fn ($x) => $x->whereIn('booth_no', $ab)->orWhereNull('booth_no')))
            ->when($status !== 'ALL', fn ($q) => $q->where('status', $status))->when($wid, fn ($q) => $q->where('worker_id', $wid))
            ->orderByRaw("case when status = 'OPEN' then 0 else 1 end")->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id')->limit(500)->get();
        $workers = Worker::where('assembly_no', $a)->where('active', true)->when($ab, fn ($q) => $q->whereIn('booth_no', $ab))->orderBy('booth_no')->orderBy('name')->get();
        $edit = $r->query('edit') ? $this->task((int) $r->query('edit')) : null;
        return view('workers.tasks', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'tasks' => $list, 'progress' => $this->tasks->progress($list),
            'workers' => $workers, 'workerNames' => $workers->pluck('name', 'id'), 'status' => $status, 'workerId' => $wid, 'edit' => $edit,
            'booths' => $a ? $this->voters->booths($a) : [], 'today' => now()->toDateString(),
            'reminders' => config('voters.tasks.reminder_channel') !== 'none' && \App\Support\Permission::messagingOn()]);
    }

    public function save(Request $r, ?int $id = null)
    {
        [$a, $ab] = $this->area($r);
        $old = $id ? $this->task($id) : null;
        $data = $r->validate([
            'title' => 'required|string|max:200', 'kind' => 'required|in:' . implode(',', TaskService::KINDS),
            'worker_id' => 'nullable|integer', 'username' => 'nullable|string|max:50', 'booth_no' => 'nullable|integer|min:1',
            'target' => 'nullable|integer|min:1|max:100000', 'due_date' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:1000',
        ]);
        $w = !empty($data['worker_id']) ? Worker::where('assembly_no', $a)->find($data['worker_id']) : null;
        abort_if(!empty($data['worker_id']) && !$w, 422, t('error.notFound'));
        $booth = $data['booth_no'] ?? $w?->booth_no;
        if ($ab && $booth && !in_array((int) $booth, $ab, true)) abort(403);
        if ($ab && !$booth) $booth = $ab[0];
        $row = ['title' => trim($data['title']), 'kind' => $data['kind'], 'worker_id' => $w?->id, 'username' => $w?->username ?: (($data['username'] ?? '') ?: null),
            'assembly_no' => $a, 'booth_no' => $booth, 'target' => $data['target'] ?? null, 'due_date' => $data['due_date'] ?? null, 'notes' => $data['notes'] ?? null];
        if ($old) {
            DB::table('worker_tasks')->where('id', $old->id)->update($row);
            $this->audit()->log('UPDATE', 'TASK', $old->id, $row['title']);
        } else {
            $id = DB::table('worker_tasks')->insertGetId($row + ['status' => 'OPEN', 'created_by' => $this->user()->username(), 'created_at' => now()]);
            $this->audit()->log('CREATE', 'TASK', $id, $row['title'] . ($w ? ' -> ' . $w->name : ''));
            if ($row['username'] && $row['username'] !== $this->user()->username()) {
                app(\App\Services\Notify\Notifier::class)->send([$row['username']], 'TASK', t('notif.newTask', $row['title']),
                    trim(($row['due_date'] ? t('task.due') . ': ' . $row['due_date'] . '. ' : '') . ($row['notes'] ?? '')) ?: null, '/my/tasks', $this->user()->username());
            }
        }
        return redirect('/workers/tasks?assemblyNo=' . $a)->with('success', t('task.saved'));
    }

    public function status(Request $r, int $id)
    {
        $t = $this->task($id);
        $done = $r->input('status') === 'DONE';
        DB::table('worker_tasks')->where('id', $t->id)->update(['status' => $done ? 'DONE' : 'OPEN', 'done_at' => $done ? now() : null]);
        $this->audit()->log('UPDATE', 'TASK', $t->id, ($done ? 'done: ' : 'reopened: ') . $t->title);
        return back()->with('success', t($done ? 'task.markedDone' : 'task.reopened'));
    }

    public function destroy(int $id)
    {
        $t = $this->task($id);
        DB::table('worker_tasks')->where('id', $t->id)->delete();
        $this->audit()->log('DELETE', 'TASK', $t->id, $t->title);
        return back()->with('success', t('task.deleted'));
    }

    /** Who checked in on a day, at what time and where. */
    public function attendance(Request $r)
    {
        [$a, $ab] = $this->area($r);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r->query('day')) ? $r->query('day') : now()->toDateString();
        $workers = Worker::where('assembly_no', $a)->where('active', true)->when($ab, fn ($q) => $q->whereIn('booth_no', $ab))->orderBy('booth_no')->orderBy('name')->get();
        $logins = $workers->pluck('username')->filter()->values()->all();
        $rows = DB::table('attendance')->where('day', $day)->where(fn ($q) => $q->where('assembly_no', $a)->orWhereIn('username', $logins ?: ['']))->get()->keyBy('username');
        // last 7 days: days present per login
        $week = DB::table('attendance')->whereIn('username', $rows->keys()->merge($logins)->unique()->values()->all() ?: [''])
            ->where('day', '>', now()->parse($day)->subDays(7)->toDateString())->where('day', '<=', $day)
            ->selectRaw('username, count(*) as n')->groupBy('username')->pluck('n', 'username');
        return view('workers.attendance', ['assemblies' => $this->voters->assemblies(), 'assemblyNo' => $a, 'day' => $day, 'workers' => $workers,
            'rows' => $rows, 'week' => $week, 'others' => $rows->except($logins)]);
    }

    // ---------------------------------------------------------------- the worker's own pages

    public function mine()
    {
        $u = $this->user()->username();
        $list = TaskService::enabled() ? DB::table('worker_tasks')->where('username', $u)
            ->where(fn ($q) => $q->where('status', 'OPEN')->orWhere('done_at', '>=', now()->subDays(14)))
            ->orderByRaw("case when status = 'OPEN' then 0 else 1 end")->orderByRaw('due_date is null')->orderBy('due_date')->get() : collect();
        return view('my.tasks', ['tasks' => $list, 'progress' => $this->tasks->progress($list), 'today' => now()->toDateString(),
            'att' => TaskService::enabled() ? $this->tasks->today($u) : null]);
    }

    public function report(Request $r, int $id)
    {
        $t = DB::table('worker_tasks')->find($id);
        abort_if(!$t || $t->username !== $this->user()->username(), 404, t('error.notFound'));
        $upd = [];
        if ($r->filled('done_count') && is_numeric($r->input('done_count'))) $upd['done_count'] = max(0, min(100000, (int) $r->input('done_count')));
        if ($r->input('status') === 'DONE') { $upd['status'] = 'DONE'; $upd['done_at'] = now(); }
        if ($upd) {
            DB::table('worker_tasks')->where('id', $t->id)->update($upd);
            $this->audit()->log('UPDATE', 'TASK', $t->id, 'worker update: ' . $t->title);
        }
        return back()->with('success', t('task.updated'));
    }

    public function myAttendance()
    {
        $u = $this->user()->username();
        return view('my.attendance', ['today' => $this->tasks->today($u),
            'history' => DB::table('attendance')->where('username', $u)->where('day', '>=', now()->subDays(30)->toDateString())->orderByDesc('day')->get()]);
    }

    public function punch(Request $r)
    {
        $assembly = $this->user()->workAssembly() ?? $this->user()->worker()?->assembly_no;
        $res = $this->tasks->punch($this->user()->username(), $assembly, $r->input('lat'), $r->input('lng'), $r->input('note'));
        if ($res !== 'already') $this->audit()->log('UPDATE', 'ATTENDANCE', null, 'check-' . $res);
        return redirect('/my/attendance')->with($res === 'already' ? 'error' : 'success', t('att.done.' . $res));
    }
}
