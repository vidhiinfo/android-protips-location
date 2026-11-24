@extends('layouts.app', ['pageTitle' => $petition->exists ? 'Edit '.$petition->tracking_no : 'Register petition'])

@section('content')
@php $districtJson = $districtsByState->map(fn ($ds) => $ds->map(fn ($d) => [$d->id, $d->name])->values()); @endphp
<form method="POST" enctype="multipart/form-data" action="{{ $petition->exists ? route('petitions.update', $petition) : route('petitions.store') }}" class="card max-w-4xl">
    @csrf @if ($petition->exists) @method('PUT') @endif
    <div class="card-b grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div><label class="label">Petitioner name *</label><input name="petitioner_name" value="{{ old('petitioner_name', $petition->petitioner_name) }}" class="input" required></div>
        <div><label class="label">Mobile</label><input name="mobile" inputmode="tel" value="{{ old('mobile', $petition->mobile) }}" class="input"></div>
        <div><label class="label">Received on *</label><input type="date" name="received_on" value="{{ old('received_on', $petition->received_on?->format('Y-m-d')) }}" class="input" required></div>
        <div>
            <label class="label">State</label>
            <select name="state_id" class="input" data-state-select="#p_district"><option value="">—</option>
                @foreach ($states as $s)<option value="{{ $s->id }}" @selected((string) old('state_id', $petition->state_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="label">District</label><select name="district_id" id="p_district" class="input" data-districts='@json($districtJson)' data-value="{{ old('district_id', $petition->district_id) }}"></select></div>
        <div class="lg:col-span-1"><label class="label">Address</label><input name="address" value="{{ old('address', $petition->address) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Subject *</label><input name="subject" value="{{ old('subject', $petition->subject) }}" class="input" required></div>
        <div>
            <label class="label">Category</label>
            <select name="category" class="input"><option value="">—</option>
                @foreach ($categories as $k => $l)<option value="{{ $k }}" @selected(old('category', $petition->category) === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-3"><label class="label">Details</label><textarea name="details" rows="5" class="input">{{ old('details', $petition->details) }}</textarea></div>
        <div><label class="label">Forwarded to</label><input name="forwarded_to" value="{{ old('forwarded_to', $petition->forwarded_to) }}" class="input" placeholder="Department / office / person"></div>
        <div>
            <label class="label">Priority</label>
            <select name="priority" class="input">@foreach ($priorities as $k => $l)<option value="{{ $k }}" @selected(old('priority', $petition->priority) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div>
            <label class="label">Status</label>
            <select name="status" class="input">@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(old('status', $petition->status) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div><label class="label">Follow-up due</label><input type="date" name="due_date" value="{{ old('due_date', $petition->due_date?->format('Y-m-d')) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Scanned petition (PDF / image)</label><input type="file" name="attachment" class="input"></div>
    </div>
    <div class="flex gap-2 border-t border-slate-100 px-4 py-3">
        <button class="btn-primary">Save</button>
        <a href="{{ $petition->exists ? route('petitions.show', $petition) : route('petitions.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
