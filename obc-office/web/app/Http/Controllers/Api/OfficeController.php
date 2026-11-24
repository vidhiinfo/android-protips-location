<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgramController;
use App\Models\AuditLog;
use App\Models\Circular;
use App\Models\Lookup;
use App\Models\Petition;
use App\Models\PetitionUpdate;
use App\Models\Program;
use App\Models\ScheduleItem;
use App\Models\Task;
use App\Services\DashboardService;
use App\Services\GreetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Read-mostly endpoints for the President's and state/district mobile apps. */
class OfficeController extends Controller
{
    public function dashboard(Request $request)
    {
        abort_unless($request->user()->canView('dashboard'), 403);
        $svc = new DashboardService($request->user());

        return [
            'totals' => $svc->totals(),
            'breakdown' => $svc->breakdown(),
            'office' => $svc->officeToday(),
            'my_tasks' => $svc->myTasks(),
        ];
    }

    public function schedule(Request $request)
    {
        abort_unless($request->user()->canView('schedule'), 403);
        $from = $request->date('from') ?? today();
        $to = $request->date('to') ?? $from->copy()->addDays(7);

        return ScheduleItem::whereBetween('start_at', [$from->startOfDay(), $to->endOfDay()])->orderBy('start_at')->get();
    }

    public function greetings(Request $request)
    {
        abort_unless($request->user()->canView('greetings'), 403);

        return (new GreetingService($request->user()))->upcoming($request->integer('days', (int) setting('greeting_days_ahead', 7)))
            ->map(fn ($g) => array_merge($g, [
                // Web photo URLs need a browser session; the app uses the token-authenticated API route.
                'photo_url' => ($g['photo_url'] ?? null) && ($g['member_id'] ?? null) ? route('api.members.photo', $g['member_id']) : null,
            ]))->values();
    }

    public function circulars(Request $request)
    {
        abort_unless($request->user()->canView('circulars'), 403);
        $uid = $request->user()->id;

        return Circular::visibleTo($request->user())->latest()->paginate(20)->through(fn ($c) => [
            'id' => $c->id, 'title' => $c->title, 'body' => $c->body, 'created_at' => $c->created_at->toIso8601String(),
            'has_attachment' => (bool) $c->attachment_path,
            'read' => $c->readers()->where('users.id', $uid)->exists(),
        ]);
    }

    public function readCircular(Request $request, Circular $circular)
    {
        abort_unless(Circular::visibleTo($request->user())->whereKey($circular->id)->exists(), 403);
        $circular->readers()->syncWithoutDetaching([$request->user()->id]);

        return response()->noContent();
    }

    public function circularAttachment(Request $request, Circular $circular)
    {
        abort_unless(Circular::visibleTo($request->user())->whereKey($circular->id)->exists(), 403);
        abort_unless($circular->attachment_path && Storage::disk('local')->exists($circular->attachment_path), 404);

        return Storage::disk('local')->download($circular->attachment_path);
    }

    public function tasks(Request $request)
    {
        abort_unless($request->user()->canView('tasks'), 403);
        $q = Task::with('assignee:id,name')->orderBy('due_date');
        if (! $request->user()->canManage('tasks') || $request->boolean('mine')) {
            $q->where('assigned_to', $request->user()->id);
        }

        return $q->when($request->filled('status'), fn ($w) => $w->where('status', $request->query('status')))->paginate(50);
    }

    public function updateTask(Request $request, Task $task)
    {
        abort_unless($request->user()->canManage('tasks') || (int) $task->assigned_to === (int) $request->user()->id, 403);
        $task->update($request->validate(['status' => ['required', Rule::in(array_keys(Lookup::options('task_status')))]]));
        AuditLog::record('status', $task, $task->status);

        return $task;
    }

    public function petitions(Request $request)
    {
        abort_unless($request->user()->canView('petitions'), 403);

        return Petition::with(['state:id,name', 'district:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('tracking_no', 'like', '%'.$request->query('q').'%')
                ->orWhere('petitioner_name', 'like', '%'.$request->query('q').'%')))
            ->latest()->paginate(30);
    }

    public function petition(Request $request, Petition $petition)
    {
        abort_unless($request->user()->canView('petitions'), 403);

        return $petition->load(['state:id,name', 'district:id,name', 'updates.user:id,name']);
    }

    public function petitionUpdate(Request $request, Petition $petition)
    {
        abort_unless($request->user()->canManage('petitions'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lookup::options('petition_status', true)))],
            'note' => 'nullable|string|max:2000',
        ]);
        $petition->update(['status' => $data['status']]);
        PetitionUpdate::create($data + ['petition_id' => $petition->id, 'user_id' => $request->user()->id]);

        return $petition->fresh('updates');
    }

    public function programs(Request $request)
    {
        abort_unless($request->user()->canView('programs'), 403);

        return Program::latest('start_date')->paginate(20);
    }

    public function submitProgramReport(Request $request, Program $program)
    {
        abort_unless($request->user()->canView('programs'), 403);

        return app(ProgramController::class)->storeReport($request, $program);
    }
}
