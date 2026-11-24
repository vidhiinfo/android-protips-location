@extends('layouts.app', ['pageTitle' => $member->exists ? 'Edit: '.$member->name : 'Add office bearer / worker', 'hideErrorSummary' => false])

@section('content')
@php
    $req = fn ($f) => in_array($f, $requiredFields, true) ? '<span class="text-red-500">*</span>' : '';
    $u = auth()->user();
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" class="space-y-6">
    @csrf
    @if ($member->exists) @method('PUT') @endif

    <div class="card">
        <div class="card-h">Position in organisation</div>
        <div class="card-b grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label">Type <span class="text-red-500">*</span></label>
                <select name="type" class="input">@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('type', $member->type) === $k)>{{ $l }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label">Level <span class="text-red-500">*</span></label>
                <select name="level" id="level" class="input">
                    @foreach ($levels as $k => $l)
                        @continue($u->role === 'state_admin' && $k === 'national')
                        @continue($u->role === 'district_admin' && in_array($k, ['national', 'state']))
                        <option value="{{ $k }}" @selected(old('level', $member->level) === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            @include('members._geo-filters', [
                'stateValue' => old('state_id', $member->state_id), 'districtValue' => old('district_id', $member->district_id),
                'districtId' => 'form_district', 'allLabel' => '— Select state —', 'districtPlaceholder' => '— Select district —',
            ])
            <div>
                <label class="label">Designation {!! $req('designation_id') !!}</label>
                <select name="designation_id" class="input">
                    <option value="">—</option>
                    @foreach ($designations->groupBy('level') as $lvl => $ds)
                        <optgroup label="{{ $levels[$lvl] ?? $lvl }}">
                            @foreach ($ds as $d)<option value="{{ $d->id }}" @selected((string) old('designation_id', $member->designation_id) === (string) $d->id)>{{ $d->title }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Mandal / Block</label><input name="mandal" value="{{ old('mandal', $member->mandal) }}" class="input"></div>
            <div><label class="label">Appointed on {!! $req('appointed_on') !!}</label><input type="date" name="appointed_on" value="{{ old('appointed_on', $member->appointed_on?->format('Y-m-d')) }}" class="input"></div>
            <div><label class="label">Tenure ends</label><input type="date" name="tenure_end" value="{{ old('tenure_end', $member->tenure_end?->format('Y-m-d')) }}" class="input"></div>
            @if ($u->isNational())
                <div>
                    <label class="label">Status</label>
                    <select name="status" class="input">@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(old('status', $member->status) === $k)>{{ $l }}</option>@endforeach</select>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-h">Personal details</div>
        <div class="card-b grid gap-4 lg:grid-cols-[180px_1fr]">
            <div class="text-center">
                <div class="mx-auto h-40 w-36 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                    <img id="photo-preview" src="{{ $member->photo_url }}" class="{{ $member->photo_url ? '' : 'hidden' }} h-full w-full object-cover" alt="">
                </div>
                <label class="btn-secondary btn-sm mt-2 cursor-pointer">
                    📷 {{ $member->photo_url ? 'Change' : 'Upload' }} photo {!! $req('photo') !!}
                    <input type="file" name="photo" accept="image/*" capture="user" class="hidden" data-preview="#photo-preview">
                </label>
                @error('photo')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div><label class="label">Full name <span class="text-red-500">*</span></label><input name="name" value="{{ old('name', $member->name) }}" class="input" required></div>
                <div><label class="label">Father's / Husband's name {!! $req('father_name') !!}</label><input name="father_name" value="{{ old('father_name', $member->father_name) }}" class="input"></div>
                <div>
                    <label class="label">Gender {!! $req('gender') !!}</label>
                    <select name="gender" class="input"><option value="">—</option>@foreach ($genders as $k => $l)<option value="{{ $k }}" @selected(old('gender', $member->gender) === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="label">Date of birth {!! $req('dob') !!}</label><input type="date" name="dob" value="{{ old('dob', $member->dob?->format('Y-m-d')) }}" class="input"></div>
                <div>
                    <label class="label">Mobile {!! $req('mobile') !!}</label><input name="mobile" inputmode="tel" value="{{ old('mobile', $member->mobile) }}" class="input">
                    @if ($errors->has('mobile') && str_contains($errors->first('mobile'), 'allow duplicate'))
                        <label class="mt-1 flex items-center gap-1.5 text-xs"><input type="checkbox" name="allow_duplicate" value="1"> Allow duplicate</label>
                    @endif
                </div>
                <div><label class="label">WhatsApp {!! $req('whatsapp') !!}</label><input name="whatsapp" inputmode="tel" value="{{ old('whatsapp', $member->whatsapp) }}" class="input" placeholder="If different from mobile"></div>
                <div><label class="label">Email {!! $req('email') !!}</label><input type="email" name="email" value="{{ old('email', $member->email) }}" class="input"></div>
                <div>
                    <label class="label">Profession {!! $req('profession') !!}</label>
                    <select name="profession" class="input"><option value="">—</option>@foreach ($professions as $k => $l)<option value="{{ $k }}" @selected(old('profession', $member->profession) === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="label">Education {!! $req('education') !!}</label>
                    <select name="education" class="input"><option value="">—</option>@foreach ($educations as $k => $l)<option value="{{ $k }}" @selected(old('education', $member->education) === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3"><label class="label">Address {!! $req('address') !!}</label><textarea name="address" rows="2" class="input">{{ old('address', $member->address) }}</textarea></div>
                <div><label class="label">Skills</label><input name="skills" value="{{ old('skills', $member->skills) }}" class="input" placeholder="Social media, legal, booth mgmt…"></div>
                <div><label class="label">Facebook</label><input name="facebook" value="{{ old('facebook', $member->facebook) }}" class="input"></div>
                <div><label class="label">X / Twitter</label><input name="twitter" value="{{ old('twitter', $member->twitter) }}" class="input"></div>
            </div>
        </div>
    </div>

    @if ($customFields->isNotEmpty())
        <div class="card">
            <div class="card-h">Additional information</div>
            <div class="card-b grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($customFields as $cf)
                    @php $v = old('extra.'.$cf->key, $member->extra[$cf->key] ?? null); @endphp
                    <div class="{{ $cf->type === 'textarea' ? 'sm:col-span-2 lg:col-span-3' : '' }}">
                        <label class="label">{{ $cf->label }} @if ($cf->required)<span class="text-red-500">*</span>@endif</label>
                        @switch($cf->type)
                            @case('textarea')<textarea name="extra[{{ $cf->key }}]" rows="3" class="input">{{ $v }}</textarea>@break
                            @case('select')
                                <select name="extra[{{ $cf->key }}]" class="input"><option value="">—</option>
                                    @foreach ($cf->optionList() as $o)<option @selected($v === $o)>{{ $o }}</option>@endforeach
                                </select>@break
                            @case('checkbox')
                                <input type="hidden" name="extra[{{ $cf->key }}]" value="0">
                                <label class="flex items-center gap-2 pt-2 text-sm"><input type="checkbox" name="extra[{{ $cf->key }}]" value="1" @checked($v)> Yes</label>@break
                            @default<input type="{{ $cf->type === 'number' ? 'number' : ($cf->type === 'date' ? 'date' : 'text') }}" name="extra[{{ $cf->key }}]" value="{{ $v }}" class="input">
                        @endswitch
                        @error('extra.'.$cf->key)<p class="err">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card card-b space-y-3">
        <div><label class="label">Internal notes</label><textarea name="notes" rows="2" class="input">{{ old('notes', $member->notes) }}</textarea></div>
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="consent" value="1" class="mt-0.5" @checked(old('consent', $member->consent))>
            <span>The person has consented to their personal data and photograph being stored and used for party organisational purposes (DPDP Act 2023).
                @if (setting('require_consent'))<span class="text-red-500">*</span>@endif</span>
        </label>
        @if (! $u->isNational() && setting('state_entries_need_approval') && ! $member->exists)
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">New records you add will be visible as <b>proposed</b> until the national office approves them.</p>
        @endif
    </div>

    <div class="flex gap-2">
        <button class="btn-primary">Save</button>
        <a href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
