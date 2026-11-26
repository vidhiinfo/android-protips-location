<div class="mb-3">
    <div class="fs-5 fw-semibold"><i class="bi bi-house-door"></i> {{ t('col.houseNo') }}: {{ $voter->house_no ?: '-' }}</div>
    <div class="text-muted">{{ $voter->fullAddress() }}</div>
    @if($voter->address_local)<div class="text-muted" dir="auto">{{ $voter->address_local }}</div>@endif
    <div class="small mt-1">{{ t('col.assemblyNo') }} <b>{{ $voter->assembly_no }}</b> &middot; <span class="badge text-bg-primary">{{ t('household.count', count($household)) }}</span></div>
</div>
<div class="table-responsive"><table class="table table-sm table-bordered table-striped align-middle mb-0">
    <thead class="table-light"><tr><th>#</th><th>{{ t('col.epicNo') }}</th><th>{{ t('col.name') }}</th><th>{{ t('list.col.relative') }}</th><th>{{ t('col.gender') }}</th>
        <th>{{ t('col.age') }}</th><th>{{ t('list.col.booth') }}</th><th>{{ t('list.col.sr') }}</th><th>{{ t('col.mobile') }}</th><th>{{ t('col.caste') }}</th></tr></thead>
    @php($families = app(\App\Services\Voters\FamilyService::class)->split($household)) @php($i = 0)
    <tbody>@foreach($families as $f => $fam)
        @if(count($families) > 1)<tr class="table-secondary"><td colspan="10" class="small fw-semibold"><i class="bi bi-diagram-3"></i> {{ t('family.label', $f + 1) }} &middot; {{ $fam[0]->name }} &middot; {{ t('family.members', count($fam)) }}</td></tr>@endif
        @foreach($fam as $h) @php($i++)
        <tr class="{{ $h->id === $voter->id ? 'table-warning' : '' }}"><td>{{ $i }}</td><td><code>{{ $h->epic_no }}</code></td>
            <td><a href="{{ url('/voters/'.$h->id) }}" target="_top">{{ $h->name }}</a>@if($h->name_local)<span class="local-text" dir="auto">{{ $h->name_local }}</span>@endif</td>
            <td>{{ $h->relationDisplay() }}@if($h->relative_name_local)<span class="local-text" dir="auto">{{ $h->relative_name_local }}</span>@endif</td>
            <td>{{ $h->genderLabel() }}</td><td>{{ $h->age }}</td><td>{{ $h->booth_no }}</td><td>{{ $h->serial_no }}</td><td>{{ $h->mobile }}</td>
            <td>{{ $h->caste }}{{ $h->sub_caste ? ' / ' . $h->sub_caste : '' }}</td></tr>
        @endforeach
    @endforeach</tbody></table></div>
@if(count($families) > 1)<div class="small text-muted mt-1">{{ t('family.note') }}</div>@endif
@include('voters.household-caste-form')
