<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Biodata;
use App\Models\BiodataDocument;
use App\Models\CustomField;
use App\Models\District;
use App\Models\Lookup;
use App\Models\State;
use App\Services\BiodataService;
use App\Services\PhotoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BiodataController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('module:biodata'),
            new Middleware('module:biodata,manage', only: ['create', 'store', 'edit', 'update', 'destroy', 'addDocuments', 'destroyDocument']),
        ];
    }

    private function svc(Request $request): BiodataService
    {
        return new BiodataService($request->user());
    }

    public function index(Request $request)
    {
        $base = $this->svc($request)->query($request);
        $rows = (clone $base)->orderByDesc('received_on')->orderByDesc('id')
            ->paginate((int) setting('per_page', 25))->withQueryString();

        return view('biodata.index', $this->formData($request) + [
            'rows' => $rows,
            'thisMonth' => $request->user()->applyGeoScope(Biodata::query())->where('received_on', '>=', now()->startOfMonth())->count(),
            'listFields' => CustomField::for('biodata')->where('show_in_list', true),
        ]);
    }

    public function create(Request $request)
    {
        $u = $request->user();

        return view('biodata.form', $this->formData($request) + ['bio' => new Biodata([
            'received_on' => today(), 'state_id' => $u->state_id, 'district_id' => $u->district_id,
        ])]);
    }

    public function store(Request $request)
    {
        $bio = $this->svc($request)->save($request);
        $dupes = $bio->earlierCopies()->count();

        if ($request->input('after') === 'new') {
            return redirect()->route('biodata.create')->with('ok', "Saved {$bio->reference_no}. Add the next one.");
        }

        return redirect()->route('biodata.show', $bio)->with('ok', "Biodata saved as {$bio->reference_no}."
            .($dupes ? " Note: {$dupes} earlier biodata of this person is already in the register." : ''));
    }

    public function show(Request $request, Biodata $biodatum)
    {
        $this->svc($request)->authorize($biodatum);
        AuditLog::record('view', $biodatum, $biodatum->reference_no);

        return view('biodata.show', [
            'bio' => $biodatum->load(['state', 'district', 'documents', 'creator']),
            'earlier' => $biodatum->earlierCopies(),
            'customFields' => CustomField::for('biodata'),
        ]);
    }

    public function edit(Request $request, Biodata $biodatum)
    {
        $this->svc($request)->authorize($biodatum);

        return view('biodata.form', $this->formData($request) + ['bio' => $biodatum->load('documents')]);
    }

    public function update(Request $request, Biodata $biodatum)
    {
        $this->svc($request)->authorize($biodatum);
        $this->svc($request)->save($request, $biodatum);

        return redirect()->route('biodata.show', $biodatum)->with('ok', 'Biodata updated.');
    }

    public function destroy(Request $request, Biodata $biodatum)
    {
        $this->svc($request)->authorize($biodatum);
        AuditLog::record('delete', $biodatum, $biodatum->reference_no.' '.$biodatum->name);
        $biodatum->delete();

        return redirect()->route('biodata.index')->with('ok', 'Biodata removed.');
    }

    /** Quick status change from the profile page. */
    public function updateStatus(Request $request, Biodata $biodatum)
    {
        abort_unless($request->user()->canManage('biodata'), 403);
        $this->svc($request)->authorize($biodatum);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Lookup::options('biodata_status', true)))]]);
        $biodatum->update($data);
        AuditLog::record('status', $biodatum, $data['status']);

        return back()->with('ok', 'Status updated.');
    }

    public function addDocuments(Request $request, Biodata $biodatum)
    {
        $svc = $this->svc($request);
        $svc->authorize($biodatum);
        $request->validate([
            'documents' => 'required|array|max:10',
            'documents.*' => ['file', 'max:'.((int) setting('biodata_max_file_mb', 10) * 1024), 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
        ]);
        $n = $svc->storeDocuments($request, $biodatum);
        AuditLog::record('update', $biodatum, "$n document(s) added");

        return back()->with('ok', "$n file(s) attached.");
    }

    public function document(Request $request, BiodataDocument $document)
    {
        $this->svc($request)->authorize($document->biodata);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        // Images and PDFs open in the browser; other files download.
        return $request->boolean('download') || ! ($document->isImage() || $document->mime === 'application/pdf')
            ? Storage::disk('local')->download($document->path, $document->original_name)
            : Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function destroyDocument(Request $request, BiodataDocument $document)
    {
        $this->svc($request)->deleteDocument($document);

        return back()->with('ok', 'File removed.');
    }

    public function photo(Request $request, Biodata $biodatum)
    {
        $this->svc($request)->authorize($biodatum);
        abort_unless($biodatum->photo_path && Storage::disk('local')->exists($biodatum->photo_path), 404);

        return Storage::disk('local')->response($biodatum->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function removePhoto(Request $request, Biodata $biodatum)
    {
        abort_unless($request->user()->canManage('biodata'), 403);
        $this->svc($request)->authorize($biodatum);
        PhotoService::delete($biodatum->photo_path);
        $biodatum->forceFill(['photo_path' => null])->save();

        return back()->with('ok', 'Photo removed.');
    }

    /** One-page printable biodata sheet in a standard format. */
    public function print(Request $request, Biodata $biodatum)
    {
        $this->svc($request)->authorize($biodatum);

        return view('biodata.print', ['bio' => $biodatum->load(['state', 'district']), 'customFields' => CustomField::for('biodata')]);
    }

    public function export(Request $request)
    {
        $query = $this->svc($request)->query($request)->orderByDesc('received_on');
        $custom = CustomField::for('biodata');
        AuditLog::record('export', null, 'biodata '.http_build_query($request->query()));

        return response()->streamDownload(function () use ($query, $custom) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Reference', 'Received on', 'Received from', 'Received via', 'Category', 'Status', 'Name',
                "Father's name", 'Gender', 'DOB', 'Mobile', 'Email', 'State', 'District', 'Mandal', 'Education', 'Profession',
                'Background', 'Remarks', 'Files'], $custom->pluck('label')->all()));
            $query->chunk(500, function ($rows) use ($out, $custom) {
                foreach ($rows as $b) {
                    fputcsv($out, array_merge([
                        $b->reference_no, $b->received_on?->toDateString(), $b->received_from, lookup_label('biodata_source', $b->received_via),
                        lookup_label('biodata_category', $b->category), lookup_label('biodata_status', $b->status), $b->name, $b->father_name,
                        lookup_label('gender', $b->gender), $b->dob?->toDateString(), $b->mobile, $b->email, $b->state?->name,
                        $b->district?->name, $b->mandal, lookup_label('education', $b->education), lookup_label('profession', $b->profession),
                        $b->background, $b->remarks, $b->documents_count,
                    ], $custom->map(fn ($cf) => $b->extra[$cf->key] ?? '')->all()));
                }
            });
            fclose($out);
        }, 'biodata-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function formData(Request $request): array
    {
        $u = $request->user();

        return [
            'states' => State::orderBy('name')->when(! $u->isNational(), fn ($q) => $q->where('id', $u->state_id))->get(),
            'districtsByState' => District::when(! $u->isNational(), fn ($q) => $q->where('state_id', $u->state_id))
                ->when($u->role === 'district_admin', fn ($q) => $q->where('id', $u->district_id))
                ->orderBy('name')->get(['id', 'state_id', 'name'])->groupBy('state_id'),
            'categories' => Lookup::options('biodata_category'),
            'sources' => Lookup::options('biodata_source'),
            'statuses' => Lookup::options('biodata_status'),
            'genders' => Lookup::options('gender'),
            'educations' => Lookup::options('education'),
            'professions' => Lookup::options('profession'),
            'customFields' => CustomField::for('biodata'),
            'requiredFields' => (array) setting('biodata_required_fields', []),
        ];
    }
}
