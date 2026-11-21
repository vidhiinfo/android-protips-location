@extends('layouts.app')
@section('title', t('tr.title'))
@section('bare', '1')
@section('bodyClass', 'print-page')
@section('content')
<div class="no-print mb-3 d-flex gap-2">
    <button class="btn btn-primary btn-sm" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
    <a class="btn btn-outline-secondary btn-sm" href="{{ url('/transport') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}">{{ t('common.back') }}</a>
</div>
<h5 class="text-center mb-0">{{ t('tr.title') }} · {{ t('col.assemblyNo') }} {{ $assemblyNo }}@if($boothNo) · {{ t('col.boothNo') }} {{ $boothNo }}@endif</h5>
<p class="text-center small text-muted">{{ count($rows) }} · {{ t('pdf.generated') }} {{ now()->format('d-m-Y H:i') }}</p>
<table>
    <thead><tr><th>#</th><th>{{ t('tr.vehicle') }}</th><th>{{ t('tr.pickupTime') }}</th><th>{{ t('col.boothNo') }}</th><th>{{ t('col.name') }}</th><th>{{ t('col.age') }}</th><th>{{ t('tr.need') }}</th><th>{{ t('tr.pickup') }}</th><th>{{ t('tr.contact') }}</th><th>✓</th></tr></thead>
    <tbody>
    @foreach($rows as $i => $t)
    @php($v = $voters[$t->voter_id] ?? null)
    <tr><td>{{ $i + 1 }}</td><td>{{ $t->vehicle }}@if($t->driver_name)<br>{{ $t->driver_name }} {{ $t->driver_mobile }}@endif</td><td>{{ $t->pickup_time }}</td><td>{{ $t->booth_no }}</td>
        <td>{{ $v?->name }}<br><small>{{ $v?->epic_no }}</small></td><td>{{ $v?->age }}</td><td>{{ t('tr.need.' . $t->need) }}</td><td>{{ $t->pickup_address }}</td><td>{{ $t->mobile }}</td><td style="width:2rem"></td></tr>
    @endforeach
    </tbody>
</table>
@endsection
