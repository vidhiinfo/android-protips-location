<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\BiodataDocument;
use App\Services\BiodataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BiodataController extends Controller
{
    private function present(Biodata $b): array
    {
        return [
            'id' => $b->id,
            'reference_no' => $b->reference_no,
            'received_on' => $b->received_on?->toDateString(),
            'received_from' => $b->received_from,
            'received_via' => $b->received_via,
            'category' => $b->category,
            'status' => $b->status,
            'status_label' => lookup_label('biodata_status', $b->status),
            'name' => $b->name,
            'father_name' => $b->father_name,
            'gender' => $b->gender,
            'dob' => $b->dob?->toDateString(),
            'mobile' => $b->mobile,
            'whatsapp' => $b->whatsapp,
            'email' => $b->email,
            'address' => $b->address,
            'state' => $b->state ? ['id' => $b->state->id, 'name' => $b->state->name] : null,
            'district' => $b->district ? ['id' => $b->district->id, 'name' => $b->district->name] : null,
            'mandal' => $b->mandal,
            'education' => $b->education,
            'profession' => $b->profession,
            'background' => $b->background,
            'remarks' => $b->remarks,
            'extra' => $b->extra ?? (object) [],
            'photo_url' => $b->photo_path ? route('api.biodata.photo', $b) : null,
            'documents' => $b->relationLoaded('documents') ? $b->documents->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->original_name, 'mime' => $d->mime, 'size' => $d->size,
                'url' => route('api.biodata.documents.show', $d),
            ]) : null,
            'documents_count' => $b->documents_count ?? $b->documents()->count(),
        ];
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->canView('biodata'), 403);
        $page = (new BiodataService($request->user()))->query($request)->orderByDesc('received_on')->orderByDesc('id')
            ->paginate(min(100, (int) $request->query('per_page', 30)));

        return $page->through(fn ($b) => $this->present($b));
    }

    public function show(Request $request, Biodata $biodatum)
    {
        abort_unless($request->user()->canView('biodata'), 403);
        (new BiodataService($request->user()))->authorize($biodatum);

        return $this->present($biodatum->load(['state', 'district', 'documents']));
    }

    /** Multipart: same fields as the web form + optional "photo" and "documents[]" (photographed pages). */
    public function store(Request $request)
    {
        abort_unless($request->user()->canManage('biodata'), 403);
        $bio = (new BiodataService($request->user()))->save($request);

        return response()->json($this->present($bio->load(['state', 'district', 'documents'])), 201);
    }

    public function addDocuments(Request $request, Biodata $biodatum)
    {
        abort_unless($request->user()->canManage('biodata'), 403);
        $svc = new BiodataService($request->user());
        $svc->authorize($biodatum);
        $request->validate(['documents' => 'required|array|max:10', 'documents.*' => 'file|max:'.((int) setting('biodata_max_file_mb', 10) * 1024).'|mimes:pdf,doc,docx,jpg,jpeg,png,webp']);
        $svc->storeDocuments($request, $biodatum);

        return $this->present($biodatum->load(['state', 'district', 'documents']));
    }

    public function photo(Request $request, Biodata $biodatum)
    {
        abort_unless($request->user()->canView('biodata'), 403);
        (new BiodataService($request->user()))->authorize($biodatum);
        abort_unless($biodatum->photo_path && Storage::disk('local')->exists($biodatum->photo_path), 404);

        return Storage::disk('local')->response($biodatum->photo_path);
    }

    public function document(Request $request, BiodataDocument $document)
    {
        abort_unless($request->user()->canView('biodata'), 403);
        (new BiodataService($request->user()))->authorize($document->biodata);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }
}
