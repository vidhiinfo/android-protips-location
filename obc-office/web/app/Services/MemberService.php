<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CustomField;
use App\Models\District;
use App\Models\Lookup;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Validation, jurisdiction rules and saving for members; shared by the web app and mobile API. */
class MemberService
{
    public function __construct(private User $user) {}

    /** Base query limited to the user's jurisdiction, with the common list filters applied. */
    public function query(Request $request): Builder
    {
        $q = $this->user->applyGeoScope(Member::query())->with(['state', 'district', 'designation']);

        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('mobile', 'like', "%$term%")
                ->orWhere('father_name', 'like', "%$term%")->orWhere('mandal', 'like', "%$term%"));
        }
        foreach (['type', 'level', 'state_id', 'district_id', 'designation_id', 'status', 'gender'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if (! $request->filled('status')) {
            $q->where('status', '!=', 'removed');
        }
        match ($request->query('missing')) {
            'photo' => $q->whereNull('photo_path'),
            'mobile' => $q->whereNull('mobile'),
            'dob' => $q->whereNull('dob'),
            'consent' => $q->where('consent', false),
            default => null,
        };

        return $q;
    }

    public function rules(?Member $member = null): array
    {
        $required = (array) setting('member_required_fields', []);
        $req = fn (string $f, string $rest) => (in_array($f, $required, true) ? 'required|' : 'nullable|').$rest;
        $lookupIn = fn (string $g) => Rule::in(array_keys(Lookup::options($g, true)));

        $rules = [
            'type' => ['required', $lookupIn('member_type')],
            'name' => 'required|string|max:150',
            'father_name' => $req('father_name', 'string|max:150'),
            'gender' => [in_array('gender', $required, true) ? 'required' : 'nullable', $lookupIn('gender')],
            'dob' => $req('dob', 'date|before:today'),
            'mobile' => $req('mobile', 'string|regex:/^[0-9+\- ]{10,15}$/'),
            'whatsapp' => $req('whatsapp', 'string|regex:/^[0-9+\- ]{10,15}$/'),
            'email' => $req('email', 'email|max:150'),
            'address' => $req('address', 'string|max:1000'),
            'profession' => $req('profession', 'string|max:100'),
            'education' => $req('education', 'string|max:100'),
            'skills' => 'nullable|string|max:255',
            'level' => ['required', $lookupIn('level')],
            'state_id' => 'nullable|exists:states,id',
            'district_id' => 'nullable|exists:districts,id',
            'mandal' => 'nullable|string|max:120',
            'designation_id' => $req('designation_id', 'exists:designations,id'),
            'appointed_on' => $req('appointed_on', 'date'),
            'tenure_end' => 'nullable|date|after_or_equal:appointed_on',
            'status' => ['nullable', $lookupIn('member_status')],
            'facebook' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'consent' => setting('require_consent') ? 'accepted' : 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
            'photo' => [in_array('photo', $required, true) && ! $member?->photo_path ? 'required' : 'nullable', 'image', 'max:5120'],
        ];

        foreach (CustomField::for('member') as $cf) {
            $rules['extra.'.$cf->key] = $cf->rules();
        }

        return $rules;
    }

    public function save(Request $request, ?Member $member = null): Member
    {
        $data = $request->validate($this->rules($member));
        $member ??= new Member;
        $isNew = ! $member->exists;

        // Jurisdiction: state/district admins can only place members inside their own area.
        if ($this->user->role === 'state_admin') {
            $data['state_id'] = $this->user->state_id;
            if ($data['level'] === 'national') {
                throw ValidationException::withMessages(['level' => 'You cannot add national-level members.']);
            }
        } elseif ($this->user->role === 'district_admin') {
            $data['state_id'] = $this->user->state_id;
            $data['district_id'] = $this->user->district_id;
            if (in_array($data['level'], ['national', 'state'], true)) {
                throw ValidationException::withMessages(['level' => 'You can only add district-level or lower members.']);
            }
        }
        if ($data['level'] === 'national') {
            $data['state_id'] = $data['state_id'] ?? null;
            $data['district_id'] = null;
        }
        if (! empty($data['district_id']) && District::find($data['district_id'])?->state_id != ($data['state_id'] ?? null)) {
            throw ValidationException::withMessages(['district_id' => 'District does not belong to the selected state.']);
        }
        if (in_array($data['level'], ['district', 'mandal', 'booth'], true) && empty($data['district_id'])) {
            throw ValidationException::withMessages(['district_id' => 'Select a district for this level.']);
        }
        if (in_array($data['level'], ['state', 'district', 'mandal', 'booth'], true) && empty($data['state_id'])) {
            throw ValidationException::withMessages(['state_id' => 'Select a state for this level.']);
        }

        if (setting('duplicate_check_mobile') && ! empty($data['mobile'])) {
            $dupe = Member::where('mobile', $data['mobile'])->where('status', '!=', 'removed')
                ->when($member->exists, fn ($q) => $q->where('id', '!=', $member->id))->first();
            if ($dupe && ! $request->boolean('allow_duplicate')) {
                throw ValidationException::withMessages(['mobile' => "This mobile is already registered to {$dupe->name} ({$dupe->area()}). Tick 'allow duplicate' to save anyway."]);
            }
        }

        // Approval workflow (configurable): entries by state/district admins start as proposed.
        if (! $this->user->isNational()) {
            if ($isNew) {
                $data['status'] = setting('state_entries_need_approval') ? 'proposed' : ($data['status'] ?? 'active');
            } elseif (setting('state_entries_need_approval')) {
                $data['status'] = $member->status; // cannot self-approve
            }
        }
        $data['status'] ??= 'active';

        $extra = $data['extra'] ?? [];
        unset($data['photo'], $data['extra']);
        $member->fill($data);
        $member->extra = $extra ?: null;
        if ($member->consent && ! $member->consent_at) {
            $member->consent_at = now();
        }
        if ($isNew) {
            $member->created_by = $this->user->id;
        }

        if ($request->hasFile('photo')) {
            PhotoService::delete($member->photo_path);
            $member->photo_path = PhotoService::store($request->file('photo'), 'photos/members');
        }

        $member->save();
        AuditLog::record($isNew ? 'create' : 'update', $member, $member->name);

        return $member;
    }

    public function authorize(Member $member): void
    {
        $ok = match ($this->user->role) {
            'state_admin' => (int) $member->state_id === (int) $this->user->state_id,
            'district_admin' => (int) $member->district_id === (int) $this->user->district_id,
            default => true,
        };
        abort_unless($ok, 403, 'This record is outside your jurisdiction.');
    }
}
