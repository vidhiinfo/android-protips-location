<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Biodata;
use App\Models\BiodataDocument;
use App\Models\CustomField;
use App\Models\District;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Biodata register: shared by the web app and the mobile API. */
class BiodataService
{
    /** Fields an admin may make mandatory (Settings → General). */
    public const OPTIONAL_FIELDS = [
        'received_from' => 'Received from', 'received_via' => 'Received via', 'category' => 'Category',
        'mobile' => 'Mobile', 'dob' => 'Date of birth', 'father_name' => "Father's / Husband's name", 'gender' => 'Gender',
        'address' => 'Address', 'state_id' => 'State', 'district_id' => 'District', 'education' => 'Education',
        'profession' => 'Profession', 'photo' => 'Photo', 'documents' => 'Biodata file (scan / PDF)',
    ];

    public function __construct(private User $user) {}

    public function query(Request $request): Builder
    {
        $q = $this->user->applyGeoScope(Biodata::query())->with(['state', 'district'])->withCount('documents');

        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('mobile', 'like', "%$term%")
                ->orWhere('reference_no', 'like', "%$term%")->orWhere('father_name', 'like', "%$term%")
                ->orWhere('received_from', 'like', "%$term%")->orWhere('background', 'like', "%$term%")
                ->orWhere('remarks', 'like', "%$term%"));
        }
        foreach (['category', 'status', 'received_via', 'state_id', 'district_id', 'gender', 'education', 'profession'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('from')) {
            $q->whereDate('received_on', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('received_on', '<=', $request->query('to'));
        }

        return $q;
    }

    public function rules(?Biodata $bio = null): array
    {
        $required = (array) setting('biodata_required_fields', []);
        $req = fn (string $f) => in_array($f, $required, true) ? 'required' : 'nullable';
        $in = fn (string $g) => Rule::in(array_keys(Lookup::options($g, true)));
        $maxKb = (int) setting('biodata_max_file_mb', 10) * 1024;
        $needsDoc = in_array('documents', $required, true) && (! $bio || $bio->documents()->doesntExist());

        $rules = [
            'received_on' => 'required|date|before_or_equal:today',
            'received_from' => [$req('received_from'), 'string', 'max:200'],
            'received_via' => [$req('received_via'), $in('biodata_source')],
            'category' => [$req('category'), $in('biodata_category')],
            'status' => ['nullable', $in('biodata_status')],
            'name' => 'required|string|max:150',
            'father_name' => [$req('father_name'), 'string', 'max:150'],
            'gender' => [$req('gender'), $in('gender')],
            'dob' => [$req('dob'), 'date', 'before:today'],
            'mobile' => [$req('mobile'), 'string', 'regex:/^[0-9+\- ]{10,15}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^[0-9+\- ]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => [$req('address'), 'string', 'max:1000'],
            'state_id' => [$req('state_id'), 'exists:states,id'],
            'district_id' => [$req('district_id'), 'exists:districts,id'],
            'mandal' => 'nullable|string|max:120',
            'education' => [$req('education'), $in('education')],
            'profession' => [$req('profession'), $in('profession')],
            'background' => 'nullable|string|max:10000',
            'remarks' => 'nullable|string|max:5000',
            'photo' => [in_array('photo', $required, true) && ! $bio?->photo_path ? 'required' : 'nullable', 'image', 'max:8192'],
            'documents' => [$needsDoc ? 'required' : 'nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:'.$maxKb, 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
        ];
        foreach (CustomField::for('biodata') as $cf) {
            $rules['extra.'.$cf->key] = $cf->rules();
        }

        return $rules;
    }

    public function save(Request $request, ?Biodata $bio = null): Biodata
    {
        $data = $request->validate($this->rules($bio));
        $bio ??= new Biodata;
        $isNew = ! $bio->exists;

        // State / district users (if given access in Settings) record biodata only for their own area.
        if ($this->user->role === 'state_admin') {
            $data['state_id'] = $this->user->state_id;
        } elseif ($this->user->role === 'district_admin') {
            $data['state_id'] = $this->user->state_id;
            $data['district_id'] = $this->user->district_id;
        }
        if (! empty($data['district_id']) && District::find($data['district_id'])?->state_id != ($data['state_id'] ?? null)) {
            throw ValidationException::withMessages(['district_id' => 'District does not belong to the selected state.']);
        }

        $extra = $data['extra'] ?? [];
        unset($data['photo'], $data['documents'], $data['extra']);
        $data['status'] ??= $bio->status ?? array_key_first(Lookup::options('biodata_status'));

        $bio->fill($data);
        $bio->extra = $extra ?: null;
        if ($isNew) {
            $bio->created_by = $this->user->id;
        }
        if ($request->hasFile('photo')) {
            PhotoService::delete($bio->photo_path);
            $bio->photo_path = PhotoService::store($request->file('photo'), 'photos/biodata');
        }
        $bio->save();
        $this->storeDocuments($request, $bio);
        AuditLog::record($isNew ? 'create' : 'update', $bio, $bio->reference_no.' '.$bio->name);

        return $bio;
    }

    public function storeDocuments(Request $request, Biodata $bio): int
    {
        $files = (array) $request->file('documents', []);
        foreach ($files as $file) {
            BiodataDocument::create([
                'biodata_id' => $bio->id,
                'path' => $file->store('biodata/'.$bio->id, 'local'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $this->user->id,
            ]);
        }

        return count($files);
    }

    public function deleteDocument(BiodataDocument $doc): void
    {
        $this->authorize($doc->biodata);
        Storage::disk('local')->delete($doc->path);
        $doc->delete();
        AuditLog::record('delete', $doc->biodata, 'document '.$doc->original_name);
    }

    public function authorize(Biodata $bio): void
    {
        $ok = match ($this->user->role) {
            'state_admin' => (int) $bio->state_id === (int) $this->user->state_id,
            'district_admin' => (int) $bio->district_id === (int) $this->user->district_id,
            default => true,
        };
        abort_unless($ok, 403, 'This biodata is outside your jurisdiction.');
    }
}
