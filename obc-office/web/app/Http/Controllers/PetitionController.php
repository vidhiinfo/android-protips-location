<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\District;
use App\Models\Lookup;
use App\Models\Petition;
use App\Models\PetitionUpdate;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PetitionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('module:petitions'),
            new Middleware('module:petitions,manage', except: ['index', 'show', 'acknowledgement', 'attachment']),
        ];
    }

    public function index(Request $request)
    {
        $q = Petition::with(['state', 'district']);
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('tracking_no', 'like', "%$term%")->orWhere('petitioner_name', 'like', "%$term%")
                ->orWhere('mobile', 'like', "%$term%")->orWhere('subject', 'like', "%$term%"));
        }
        foreach (['status', 'category', 'state_id', 'priority'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->query('overdue')) {
            $q->where('status', '!=', setting('petition_closed_status', 'closed'))->whereDate('due_date', '<', today());
        }

        return view('petitions.index', [
            'petitions' => $q->latest()->paginate((int) setting('per_page', 25))->withQueryString(),
            'states' => State::orderBy('name')->pluck('name', 'id'),
            'statuses' => Lookup::options('petition_status', true),
            'categories' => Lookup::options('petition_category', true),
            'priorities' => Lookup::options('priority', true),
        ]);
    }

    public function create()
    {
        return view('petitions.form', $this->formData() + ['petition' => new Petition([
            'received_on' => today(), 'status' => 'received', 'priority' => 'normal',
            'due_date' => today()->addDays((int) setting('petition_default_due_days', 15)),
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $petition = new Petition($data);
        $petition->created_by = $request->user()->id;
        $petition->save();
        $this->saveAttachment($request, $petition);
        PetitionUpdate::create(['petition_id' => $petition->id, 'status' => $petition->status, 'note' => 'Petition registered.', 'user_id' => $request->user()->id]);
        AuditLog::record('create', $petition, $petition->tracking_no);

        return redirect()->route('petitions.show', $petition)->with('ok', "Petition registered: {$petition->tracking_no}");
    }

    public function show(Petition $petition)
    {
        return view('petitions.show', [
            'petition' => $petition->load(['state', 'district', 'updates.user']),
            'statuses' => Lookup::options('petition_status'),
        ]);
    }

    public function edit(Petition $petition)
    {
        return view('petitions.form', $this->formData() + ['petition' => $petition]);
    }

    public function update(Request $request, Petition $petition)
    {
        $petition->update($this->validated($request));
        $this->saveAttachment($request, $petition);
        AuditLog::record('update', $petition, $petition->tracking_no);

        return redirect()->route('petitions.show', $petition)->with('ok', 'Petition updated.');
    }

    public function destroy(Petition $petition)
    {
        AuditLog::record('delete', $petition, $petition->tracking_no);
        $petition->delete();

        return redirect()->route('petitions.index')->with('ok', 'Petition deleted.');
    }

    /** Add a follow-up note and/or change status; appears in the timeline. */
    public function addUpdate(Request $request, Petition $petition)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lookup::options('petition_status', true)))],
            'note' => 'nullable|string|max:2000',
            'forwarded_to' => 'nullable|string|max:255',
        ]);
        $petition->update(array_filter(['status' => $data['status'], 'forwarded_to' => $data['forwarded_to'] ?? null]));
        PetitionUpdate::create(['petition_id' => $petition->id, 'status' => $data['status'],
            'note' => trim(($data['note'] ?? '').(! empty($data['forwarded_to']) ? "\nForwarded to: {$data['forwarded_to']}" : '')), 'user_id' => $request->user()->id]);
        AuditLog::record('status', $petition, $data['status']);

        return back()->with('ok', 'Update added.');
    }

    /** Printable acknowledgement slip for the petitioner. */
    public function acknowledgement(Petition $petition)
    {
        return view('petitions.ack', ['petition' => $petition->load(['state', 'district'])]);
    }

    public function attachment(Petition $petition)
    {
        abort_unless($petition->attachment_path && Storage::disk('local')->exists($petition->attachment_path), 404);

        return Storage::disk('local')->download($petition->attachment_path);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'petitioner_name' => 'required|string|max:150',
            'mobile' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:1000',
            'state_id' => 'nullable|exists:states,id',
            'district_id' => 'nullable|exists:districts,id',
            'category' => ['nullable', Rule::in(array_keys(Lookup::options('petition_category', true)))],
            'subject' => 'required|string|max:255',
            'details' => 'nullable|string|max:10000',
            'forwarded_to' => 'nullable|string|max:255',
            'priority' => ['required', Rule::in(array_keys(Lookup::options('priority', true)))],
            'status' => ['required', Rule::in(array_keys(Lookup::options('petition_status', true)))],
            'received_on' => 'required|date',
            'due_date' => 'nullable|date',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);
        unset($data['attachment']);

        return $data;
    }

    private function saveAttachment(Request $request, Petition $petition): void
    {
        if ($request->hasFile('attachment')) {
            if ($petition->attachment_path) {
                Storage::disk('local')->delete($petition->attachment_path);
            }
            $petition->update(['attachment_path' => $request->file('attachment')->store('petitions', 'local')]);
        }
    }

    private function formData(): array
    {
        return [
            'states' => State::orderBy('name')->get(),
            'districtsByState' => District::orderBy('name')->get(['id', 'state_id', 'name'])->groupBy('state_id'),
            'statuses' => Lookup::options('petition_status'),
            'categories' => Lookup::options('petition_category'),
            'priorities' => Lookup::options('priority'),
        ];
    }
}
