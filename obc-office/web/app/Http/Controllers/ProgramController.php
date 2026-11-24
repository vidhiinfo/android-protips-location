<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\District;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Models\State;
use App\Services\PhotoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * National office announces a programme/campaign; states and districts submit event reports with photos.
 * "view" permission = see programmes + submit reports for own area; "manage" = create programmes, see all reports.
 */
class ProgramController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('module:programs'),
            new Middleware('module:programs,manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        return view('programs.index', ['programs' => Program::withCount('reports')->latest('start_date')->paginate(20)]);
    }

    public function create()
    {
        return view('programs.form', ['program' => new Program(['start_date' => today()])]);
    }

    public function store(Request $request)
    {
        $p = new Program($this->validated($request));
        $p->created_by = $request->user()->id;
        $p->save();
        AuditLog::record('create', $p, $p->name);

        return redirect()->route('programs.show', $p)->with('ok', 'Programme created.');
    }

    public function edit(Program $program)
    {
        return view('programs.form', ['program' => $program]);
    }

    public function update(Request $request, Program $program)
    {
        $program->update($this->validated($request));

        return redirect()->route('programs.show', $program)->with('ok', 'Programme updated.');
    }

    public function destroy(Program $program)
    {
        AuditLog::record('delete', $program, $program->name);
        $program->delete();

        return redirect()->route('programs.index')->with('ok', 'Programme deleted.');
    }

    public function show(Request $request, Program $program)
    {
        $user = $request->user();
        $reports = $user->applyGeoScope($program->reports()->getQuery())->with(['state', 'district', 'submitter'])
            ->when($request->filled('state_id'), fn ($q) => $q->where('state_id', $request->query('state_id')))
            ->latest('event_date')->paginate(20)->withQueryString();

        // Which states have / have not reported (national view).
        $coverage = null;
        if ($user->isNational()) {
            $reported = $program->reports()->select('state_id', DB::raw('COUNT(*) as c'), DB::raw('SUM(attendance) as a'))
                ->groupBy('state_id')->get()->keyBy('state_id');
            $coverage = State::orderBy('name')->get()->map(fn ($s) => [
                'state' => $s, 'reports' => (int) ($reported[$s->id]->c ?? 0), 'attendance' => (int) ($reported[$s->id]->a ?? 0),
            ]);
        }

        return view('programs.show', [
            'program' => $program,
            'reports' => $reports,
            'coverage' => $coverage,
            'states' => State::orderBy('name')->when(! $user->isNational(), fn ($q) => $q->where('id', $user->state_id))->get(),
            'districtsByState' => District::orderBy('name')
                ->when(! $user->isNational(), fn ($q) => $q->where('state_id', $user->state_id))
                ->when($user->role === 'district_admin', fn ($q) => $q->where('id', $user->district_id))
                ->get(['id', 'state_id', 'name'])->groupBy('state_id'),
        ]);
    }

    public function storeReport(Request $request, Program $program)
    {
        $user = $request->user();
        $data = $request->validate([
            'state_id' => 'required|exists:states,id',
            'district_id' => 'nullable|exists:districts,id',
            'event_date' => 'required|date',
            'venue' => 'nullable|string|max:255',
            'attendance' => 'nullable|integer|min:0|max:10000000',
            'summary' => 'nullable|string|max:5000',
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|max:8192',
        ]);
        if (! $user->isNational()) {
            $data['state_id'] = $user->state_id;
        }
        if ($user->role === 'district_admin') {
            $data['district_id'] = $user->district_id;
        }

        $report = new ProgramReport(collect($data)->except('photos')->all());
        $report->program_id = $program->id;
        $report->submitted_by = $user->id;
        $report->photos = collect($request->file('photos', []))
            ->map(fn ($f) => PhotoService::store($f, 'photos/programs'))->values()->all();
        $report->save();
        AuditLog::record('report', $report, $program->name);

        if ($request->expectsJson()) {
            return response()->json($report, 201);
        }

        return back()->with('ok', 'Report submitted. Thank you!');
    }

    public function reportPhoto(Request $request, ProgramReport $report, int $index)
    {
        $user = $request->user();
        abort_unless($user->canView('programs'), 403);
        abort_unless($user->applyGeoScope(ProgramReport::query())->whereKey($report->id)->exists(), 403);
        $path = $report->photos[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:10000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
    }
}
