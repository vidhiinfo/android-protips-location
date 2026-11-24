<?php

namespace App\Services;

use App\Models\Biodata;
use App\Models\Designation;
use App\Models\District;
use App\Models\Member;
use App\Models\Petition;
use App\Models\ScheduleItem;
use App\Models\State;
use App\Models\Task;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;

/** Builds dashboard numbers; shared by the web dashboard and the mobile API. */
class DashboardService
{
    public function __construct(private User $user) {}

    private function members()
    {
        return $this->user->applyGeoScope(Member::query())->where('status', '!=', 'removed');
    }

    public function totals(): array
    {
        $m = $this->members();

        return [
            'bearers' => (clone $m)->where('type', 'bearer')->where('status', 'active')->count(),
            'workers' => (clone $m)->where('type', 'worker')->where('status', 'active')->count(),
            'proposed' => (clone $m)->where('status', 'proposed')->count(),
            'missing_photo' => (clone $m)->whereNull('photo_path')->count(),
            'missing_mobile' => (clone $m)->whereNull('mobile')->count(),
            'added_this_month' => (clone $m)->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /** Per-state (or per-district for state admins) breakdown. */
    public function breakdown(): array
    {
        $byDistrict = $this->user->role !== 'super_admin' && $this->user->role !== 'national_staff';
        $group = $byDistrict ? 'district_id' : 'state_id';

        $rows = $this->members()
            ->select($group.' as gid',
                DB::raw("SUM(CASE WHEN type='bearer' AND status='active' THEN 1 ELSE 0 END) as bearers"),
                DB::raw("SUM(CASE WHEN type='worker' AND status='active' THEN 1 ELSE 0 END) as workers"),
                DB::raw('SUM(CASE WHEN photo_path IS NULL THEN 1 ELSE 0 END) as no_photo'),
                DB::raw('MAX(updated_at) as last_update'))
            ->groupBy($group)->get()->keyBy('gid');

        $areas = $byDistrict
            ? District::when($this->user->role === 'state_admin', fn ($q) => $q->where('state_id', $this->user->state_id))
                ->when($this->user->role === 'district_admin', fn ($q) => $q->where('id', $this->user->district_id))
                ->orderBy('name')->get(['id', 'name'])
            : State::orderBy('name')->get(['id', 'name']);

        // Sanctioned seats at the level directly managed (state level for national view, district for state view).
        $level = $byDistrict ? 'district' : 'state';
        $sanctioned = (int) Designation::where('level', $level)->sum('max_count');
        $filled = $this->members()->where('type', 'bearer')->where('status', 'active')->where('level', $level)
            ->select($group.' as gid', DB::raw('COUNT(*) as c'))->groupBy($group)->pluck('c', 'gid');

        return [
            'by' => $byDistrict ? 'District' : 'State',
            'rows' => $areas->map(function ($a) use ($rows, $sanctioned, $filled) {
                $r = $rows->get($a->id);
                $f = (int) ($filled[$a->id] ?? 0);

                return [
                    'id' => $a->id, 'name' => $a->name,
                    'bearers' => (int) ($r->bearers ?? 0), 'workers' => (int) ($r->workers ?? 0),
                    'no_photo' => (int) ($r->no_photo ?? 0), 'last_update' => $r->last_update ?? null,
                    'posts_filled' => $f, 'posts_sanctioned' => $sanctioned,
                    'vacancies' => max(0, $sanctioned - $f),
                ];
            })->values()->all(),
        ];
    }

    public function officeToday(): array
    {
        if (! $this->user->canView('schedule') && ! $this->user->canView('petitions') && ! $this->user->canView('biodata')) {
            return [];
        }
        $closed = setting('petition_closed_status', 'closed');

        return [
            'schedule' => $this->user->canView('schedule')
                ? ScheduleItem::whereDate('start_at', today())->orderBy('start_at')->get() : collect(),
            'visitors_today' => $this->user->canView('visitors') ? Visitor::whereDate('visited_at', today())->count() : null,
            'petitions_open' => $this->user->canView('petitions') ? Petition::where('status', '!=', $closed)->count() : null,
            'biodata_month' => $this->user->canView('biodata')
                ? $this->user->applyGeoScope(Biodata::query())->where('received_on', '>=', now()->startOfMonth())->count() : null,
            'petitions_overdue' => $this->user->canView('petitions')
                ? Petition::where('status', '!=', $closed)->whereDate('due_date', '<', today())->count() : null,
        ];
    }

    public function myTasks()
    {
        return Task::where('assigned_to', $this->user->id)->where('status', '!=', 'done')->orderBy('due_date')->limit(8)->get();
    }
}
