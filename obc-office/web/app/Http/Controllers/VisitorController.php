<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VisitorController extends SimpleCrudController
{
    protected string $model = Visitor::class;

    protected string $module = 'visitors';

    protected string $routeName = 'visitors';

    protected string $title = 'Visitor Register';

    protected string $singular = 'Visitor';

    protected array $search = ['name', 'mobile', 'from_place', 'purpose', 'referred_by'];

    protected array $filters = ['status'];

    protected ?string $dateColumn = 'visited_at';

    protected string $orderBy = 'visited_at';

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Visitor name', 'rules' => 'required|string|max:150', 'list' => true],
            'mobile' => ['label' => 'Mobile', 'type' => 'tel', 'rules' => 'nullable|string|max:15', 'list' => true],
            'from_place' => ['label' => 'Coming from', 'rules' => 'nullable|string|max:150', 'list' => true],
            'purpose' => ['label' => 'Purpose', 'rules' => 'nullable|string|max:255', 'list' => true],
            'referred_by' => ['label' => 'Referred by', 'rules' => 'nullable|string|max:150'],
            'visited_at' => ['label' => 'Visit time', 'type' => 'datetime', 'rules' => 'required|date', 'list' => true],
            'status' => ['label' => 'Status', 'type' => 'lookup:visitor_status', 'rules' => 'required', 'list' => true],
            'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => 'nullable|string|max:2000'],
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        if (! $request->hasAny(['from', 'to', 'q'])) {
            $request->query->set('from', now()->toDateString());
        }

        return Visitor::query();
    }

    protected function defaults(): array
    {
        return ['visited_at' => now(), 'status' => 'waiting'];
    }
}
