<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\District;
use App\Models\Lookup;
use App\Models\Member;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/** States, districts and designations (posts with sanctioned seats per level). */
class MasterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('module:masters,manage')];
    }

    public function index(Request $request)
    {
        $stateId = $request->query('state_id');

        return view('masters.index', [
            'states' => State::withCount(['districts', 'members'])->orderBy('name')->get(),
            'selectedState' => $stateId ? State::with('districts')->find($stateId) : null,
            'designations' => Designation::orderBy('level')->orderBy('seniority')->get()->groupBy('level'),
            'levels' => Lookup::options('level', true),
        ]);
    }

    public function storeState(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100|unique:states,name', 'code' => 'nullable|string|max:5', 'is_ut' => 'nullable|boolean']);
        $s = State::create($data + ['is_ut' => $request->boolean('is_ut')]);
        AuditLog::record('create', $s, $s->name);

        return back()->with('ok', 'State added.');
    }

    /** Add many districts at once: one name per line. */
    public function storeDistricts(Request $request, State $state)
    {
        $data = $request->validate(['names' => 'required|string|max:20000']);
        $names = collect(preg_split('/\r?\n|,/', $data['names']))->map(fn ($n) => trim($n))->filter()->unique();
        $added = 0;
        foreach ($names as $n) {
            $added += District::firstOrCreate(['state_id' => $state->id, 'name' => $n])->wasRecentlyCreated ? 1 : 0;
        }
        AuditLog::record('create', $state, "$added districts added");

        return redirect()->route('masters.index', ['state_id' => $state->id])->with('ok', "$added district(s) added.");
    }

    public function updateDistrict(Request $request, District $district)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('districts')->where('state_id', $district->state_id)->ignore($district)]]);
        $district->update($data);

        return back()->with('ok', 'District renamed.');
    }

    public function destroyDistrict(District $district)
    {
        if (Member::where('district_id', $district->id)->exists()) {
            return back()->withErrors(['district' => 'This district has members; move them before deleting.']);
        }
        $district->delete();

        return back()->with('ok', 'District deleted.');
    }

    public function storeDesignation(Request $request)
    {
        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys(Lookup::options('level', true)))],
            'title' => ['required', 'string', 'max:100', Rule::unique('designations')->where('level', $request->input('level'))],
            'seniority' => 'required|integer|min:1|max:999',
            'max_count' => 'required|integer|min:0|max:999',
        ]);
        $d = Designation::create($data);
        AuditLog::record('create', $d, "{$d->level}: {$d->title}");

        return back()->with('ok', 'Designation added.');
    }

    public function updateDesignation(Request $request, Designation $designation)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100', Rule::unique('designations')->where('level', $designation->level)->ignore($designation)],
            'seniority' => 'required|integer|min:1|max:999',
            'max_count' => 'required|integer|min:0|max:999',
        ]);
        $designation->update($data);

        return back()->with('ok', 'Designation updated.');
    }

    public function destroyDesignation(Designation $designation)
    {
        if (Member::where('designation_id', $designation->id)->exists()) {
            return back()->withErrors(['designation' => 'Members hold this post; reassign them before deleting.']);
        }
        $designation->delete();

        return back()->with('ok', 'Designation deleted.');
    }
}
