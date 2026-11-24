<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lookup;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

/** Users with "manage" see and assign all tasks; users with "view" see only tasks assigned to them. */
class TaskController extends SimpleCrudController
{
    protected string $model = Task::class;

    protected string $module = 'tasks';

    protected string $routeName = 'tasks';

    protected string $title = 'Tasks & Follow-ups';

    protected string $singular = 'Task';

    protected array $search = ['title', 'details'];

    protected array $filters = ['status', 'priority', 'assigned_to'];

    protected string $orderBy = 'due_date';

    protected string $orderDir = 'asc';

    public static function middleware(): array
    {
        return [
            new Middleware('module:tasks'),
            new Middleware('module:tasks,manage', except: ['index', 'show', 'updateStatus']),
        ];
    }

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'Task', 'rules' => 'required|string|max:255', 'list' => true],
            'assigned_to' => ['label' => 'Assigned to', 'type' => 'user', 'rules' => 'nullable', 'list' => true],
            'due_date' => ['label' => 'Due date', 'type' => 'date', 'rules' => 'nullable|date', 'list' => true],
            'priority' => ['label' => 'Priority', 'type' => 'lookup:priority', 'rules' => 'required', 'list' => true],
            'status' => ['label' => 'Status', 'type' => 'lookup:task_status', 'rules' => 'required', 'list' => true],
            'details' => ['label' => 'Details', 'type' => 'textarea', 'rules' => 'nullable|string|max:5000'],
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        $q = Task::query();
        if (! $request->user()->canManage('tasks')) {
            $q->where('assigned_to', $request->user()->id);
        }

        return $q;
    }

    protected function defaults(): array
    {
        return ['priority' => 'normal', 'status' => 'open', 'due_date' => now()->addDays(3)->toDateString()];
    }

    /** Assignees (e.g. state presidents) can update the status of their own tasks. */
    public function updateStatus(Request $request, Task $task)
    {
        abort_unless($request->user()->canManage('tasks') || (int) $task->assigned_to === (int) $request->user()->id, 403);
        $data = $request->validate(['status' => 'required|in:'.implode(',', array_keys(Lookup::options('task_status')))]);
        $task->update($data);
        AuditLog::record('status', $task, $data['status']);

        return back()->with('ok', 'Task status updated.');
    }
}
