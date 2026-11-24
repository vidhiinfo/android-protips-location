{{-- State + dependent district select used by list, directory and forms --}}
@php
    $districtJson = $districtsByState->map(fn ($ds) => $ds->map(fn ($d) => [$d->id, $d->name])->values());
@endphp
<div>
    <label class="label">State</label>
    <select name="state_id" class="input" data-state-select="#{{ $districtId ?? 'filter_district' }}" @if ($states->count() === 1 && ! auth()->user()->isNational()) disabled @endif>
        @if (auth()->user()->isNational())<option value="">{{ $allLabel ?? 'All states' }}</option>@endif
        @foreach ($states as $s)
            <option value="{{ $s->id }}" @selected((string) ($stateValue ?? request('state_id')) === (string) $s->id)>{{ $s->name }}</option>
        @endforeach
    </select>
</div>
<div>
    <label class="label">District</label>
    <select name="district_id" id="{{ $districtId ?? 'filter_district' }}" class="input" data-districts='@json($districtJson)'
            data-value="{{ $districtValue ?? request('district_id') }}" data-placeholder="{{ $districtPlaceholder ?? 'All districts' }}"
            @if (auth()->user()->role === 'district_admin') disabled @endif>
        <option value="">{{ $districtPlaceholder ?? 'All districts' }}</option>
    </select>
</div>
