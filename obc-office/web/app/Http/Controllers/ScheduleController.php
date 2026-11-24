<?php

namespace App\Http\Controllers;

use App\Models\ScheduleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ScheduleController extends SimpleCrudController
{
    protected string $model = ScheduleItem::class;

    protected string $module = 'schedule';

    protected string $routeName = 'schedule';

    protected string $title = 'Schedule & Tours';

    protected string $singular = 'Schedule entry';

    protected array $search = ['title', 'location', 'contact_person'];

    protected array $filters = ['kind', 'status'];

    protected ?string $dateColumn = 'start_at';

    protected string $orderBy = 'start_at';

    protected string $orderDir = 'asc';

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'Title', 'rules' => 'required|string|max:200', 'list' => true],
            'kind' => ['label' => 'Type', 'type' => 'lookup:schedule_kind', 'rules' => 'required', 'list' => true],
            'start_at' => ['label' => 'Starts', 'type' => 'datetime', 'rules' => 'required|date', 'list' => true],
            'end_at' => ['label' => 'Ends', 'type' => 'datetime', 'rules' => 'nullable|date|after_or_equal:start_at'],
            'location' => ['label' => 'Location', 'rules' => 'nullable|string|max:200', 'list' => true],
            'contact_person' => ['label' => 'Local contact', 'rules' => 'nullable|string|max:120'],
            'contact_mobile' => ['label' => 'Contact mobile', 'type' => 'tel', 'rules' => 'nullable|string|max:15'],
            'status' => ['label' => 'Status', 'type' => 'lookup:schedule_status', 'rules' => 'required', 'list' => true],
            'notes' => ['label' => 'Notes / briefing', 'type' => 'textarea', 'rules' => 'nullable|string|max:5000'],
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        // Default view: upcoming entries from today.
        if (! $request->hasAny(['from', 'to', 'q'])) {
            $request->query->set('from', now()->toDateString());
        }

        return ScheduleItem::query();
    }

    protected function defaults(): array
    {
        return ['kind' => 'meeting', 'status' => 'planned', 'start_at' => now()->addHour()->startOfHour()];
    }
}
