@extends('layouts.app', ['pageTitle' => $bio->exists ? 'Edit biodata '.$bio->reference_no : 'Record a biodata'])

@section('content')
@php
    $req = fn ($f) => in_array($f, $requiredFields, true) ? '<span class="text-red-500">*</span>' : '';
    $sel = function ($name, $opts, $current) {
        $h = '<option value="">—</option>';
        foreach ($opts as $k => $l) { $h .= '<option value="'.e($k).'"'.((string) $current === (string) $k ? ' selected' : '').'>'.e($l).'</option>'; }
        return $h;
    };
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $bio->exists ? route('biodata.update', $bio) : route('biodata.store') }}" class="space-y-6">
    @csrf @if ($bio->exists) @method('PUT') @endif

    <div class="card">
        <div class="card-h">Receipt details</div>
        <div class="card-b grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div><label class="label">Received on <span class="text-red-500">*</span></label><input type="date" name="received_on" value="{{ old('received_on', $bio->received_on?->format('Y-m-d')) }}" class="input" required></div>
            <div class="lg:col-span-2"><label class="label">Received from {!! $req('received_from') !!}</label><input name="received_from" value="{{ old('received_from', $bio->received_from) }}" class="input" placeholder="e.g. UP State President / Shri X, MLA"></div>
            <div><label class="label">Received via {!! $req('received_via') !!}</label><select name="received_via" class="input">{!! $sel('received_via', $sources, old('received_via', $bio->received_via)) !!}</select></div>
            <div><label class="label">Category {!! $req('category') !!}</label><select name="category" class="input">{!! $sel('category', $categories, old('category', $bio->category)) !!}</select></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Person</div>
        <div class="card-b grid gap-4 lg:grid-cols-[180px_1fr]">
            <div class="text-center">
                <div class="mx-auto h-40 w-36 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                    <img id="photo-preview" src="{{ $bio->photoUrl() }}" class="{{ $bio->photoUrl() ? '' : 'hidden' }} h-full w-full object-cover" alt="">
                </div>
                <label class="btn-secondary btn-sm mt-2 cursor-pointer">📷 {{ $bio->photoUrl() ? 'Change' : 'Add' }} photo {!! $req('photo') !!}
                    <input type="file" name="photo" accept="image/*" class="hidden" data-preview="#photo-preview">
                </label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2"><label class="label">Full name <span class="text-red-500">*</span></label><input name="name" value="{{ old('name', $bio->name) }}" class="input" required></div>
                <div class="sm:col-span-2"><label class="label">Father's / Husband's name {!! $req('father_name') !!}</label><input name="father_name" value="{{ old('father_name', $bio->father_name) }}" class="input"></div>
                <div><label class="label">Gender {!! $req('gender') !!}</label><select name="gender" class="input">{!! $sel('gender', $genders, old('gender', $bio->gender)) !!}</select></div>
                <div><label class="label">Date of birth {!! $req('dob') !!}</label><input type="date" name="dob" value="{{ old('dob', $bio->dob?->format('Y-m-d')) }}" class="input"></div>
                <div><label class="label">Mobile {!! $req('mobile') !!}</label><input name="mobile" inputmode="tel" value="{{ old('mobile', $bio->mobile) }}" class="input"></div>
                <div><label class="label">WhatsApp</label><input name="whatsapp" inputmode="tel" value="{{ old('whatsapp', $bio->whatsapp) }}" class="input"></div>
                <div class="sm:col-span-2"><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $bio->email) }}" class="input"></div>
                <div><label class="label">Education {!! $req('education') !!}</label><select name="education" class="input">{!! $sel('education', $educations, old('education', $bio->education)) !!}</select></div>
                <div><label class="label">Profession {!! $req('profession') !!}</label><select name="profession" class="input">{!! $sel('profession', $professions, old('profession', $bio->profession)) !!}</select></div>
                @include('members._geo-filters', [
                    'stateValue' => old('state_id', $bio->state_id), 'districtValue' => old('district_id', $bio->district_id),
                    'districtId' => 'bio_district', 'allLabel' => '— State —', 'districtPlaceholder' => '— District —',
                ])
                <div><label class="label">Mandal / Block</label><input name="mandal" value="{{ old('mandal', $bio->mandal) }}" class="input"></div>
                <div class="sm:col-span-2 lg:col-span-4"><label class="label">Address {!! $req('address') !!}</label><textarea name="address" rows="2" class="input">{{ old('address', $bio->address) }}</textarea></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Background & notes</div>
        <div class="card-b grid gap-4 lg:grid-cols-2">
            <div><label class="label">Political / social background (positions held, work done…)</label><textarea name="background" rows="5" class="input">{{ old('background', $bio->background) }}</textarea></div>
            <div><label class="label">Office remarks</label><textarea name="remarks" rows="5" class="input">{{ old('remarks', $bio->remarks) }}</textarea></div>
            @foreach ($customFields as $cf)
                @php $v = old('extra.'.$cf->key, $bio->extra[$cf->key] ?? null); @endphp
                <div>
                    <label class="label">{{ $cf->label }} @if ($cf->required)<span class="text-red-500">*</span>@endif</label>
                    @switch($cf->type)
                        @case('textarea')<textarea name="extra[{{ $cf->key }}]" rows="3" class="input">{{ $v }}</textarea>@break
                        @case('select')<select name="extra[{{ $cf->key }}]" class="input"><option value="">—</option>@foreach ($cf->optionList() as $o)<option @selected($v === $o)>{{ $o }}</option>@endforeach</select>@break
                        @case('checkbox')<input type="hidden" name="extra[{{ $cf->key }}]" value="0"><label class="flex items-center gap-2 pt-2 text-sm"><input type="checkbox" name="extra[{{ $cf->key }}]" value="1" @checked($v)> Yes</label>@break
                        @default<input type="{{ $cf->type === 'number' ? 'number' : ($cf->type === 'date' ? 'date' : 'text') }}" name="extra[{{ $cf->key }}]" value="{{ $v }}" class="input">
                    @endswitch
                    @error('extra.'.$cf->key)<p class="err">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-h">Biodata file(s) {!! $req('documents') !!}</div>
        <div class="card-b space-y-3">
            @if ($bio->exists && $bio->documents->isNotEmpty())
                <ul class="text-sm">@foreach ($bio->documents as $d)<li>📎 {{ $d->original_name }} <span class="text-xs text-slate-400">({{ $d->sizeLabel() }})</span></li>@endforeach</ul>
            @endif
            <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,image/*" class="input">
            <p class="text-xs text-slate-500">Upload the scanned biodata, PDF or Word file and any attachments (up to 10 files, {{ setting('biodata_max_file_mb', 10) }} MB each). On a phone you can photograph the pages directly.</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if ($bio->exists)
            <div class="w-48"><select name="status" class="input">@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(old('status', $bio->status) === $k)>{{ $l }}</option>@endforeach</select></div>
        @endif
        <button class="btn-primary">Save</button>
        @unless ($bio->exists)<button name="after" value="new" class="btn-secondary">Save & add another</button>@endunless
        <a href="{{ $bio->exists ? route('biodata.show', $bio) : route('biodata.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
