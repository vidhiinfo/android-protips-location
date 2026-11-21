@extends('layouts.app')
@section('title', t('tr.title'))
@section('content')
@php($stCls = ['REQUESTED' => 'text-bg-danger', 'ASSIGNED' => 'text-bg-warning', 'PICKED' => 'text-bg-info', 'VOTED' => 'text-bg-success', 'CANCELLED' => 'text-bg-secondary'])
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-truck"></i> {{ t('tr.title') }}</h4><div class="small text-muted">{{ t('tr.intro') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @endunless
            <div><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
            <input type="hidden" name="status" value="{{ $status }}">
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/transport/print') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}" target="_blank"><i class="bi bi-printer"></i> {{ t('tr.print') }}</a>
    </div>
    @unless($hasElection)<div class="alert alert-info small py-2">{{ t('tr.noElection') }}</div>@endunless
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm {{ !$status ? 'btn-dark' : 'btn-outline-secondary' }}" href="?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}">{{ t('common.all') }} <span class="badge text-bg-light">{{ array_sum($counts) }}</span></a>
        @foreach(\App\Http\Controllers\TransportController::STATUSES as $s)
        <a class="btn btn-sm {{ $status === $s ? 'btn-dark' : 'btn-outline-secondary' }}" href="?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo, 'status' => $s]) }}">{{ t('tr.st.' . $s) }} <span class="badge text-bg-light">{{ $counts[$s] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if($prefill || $eligible->count())
    <form method="post" action="{{ url('/transport') }}" class="card mb-3">@csrf
        <input type="hidden" name="boothNo" value="{{ $boothNo }}">
        <div class="card-header py-2 fw-semibold">{{ $prefill ? t('tr.addFor', $prefill->name) : t('tr.eligible', count($eligible), $boothNo) }}</div>
        <div class="card-body py-2">
            @if($prefill)<input type="hidden" name="voter_ids[]" value="{{ $prefill->id }}">
            <div class="small mb-2">{{ $prefill->epic_no }} · {{ t('col.age') }} {{ $prefill->age }} · {{ t('col.boothNo') }} {{ $prefill->booth_no }} · {{ $prefill->fullAddress() }}</div>
            @else
            <div class="d-flex flex-wrap gap-2 mb-2" style="max-height:9rem;overflow:auto">
                @foreach($eligible as $v)<label class="border rounded px-2 py-1 small"><input type="checkbox" name="voter_ids[]" value="{{ $v->id }}" class="form-check-input me-1">{{ $v->serial_no }}. {{ $v->name }} ({{ $v->age }})</label>@endforeach
            </div>
            @endif
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('tr.reason') }}</label><select name="reason" class="form-select form-select-sm">
                    @foreach(\App\Http\Controllers\TransportController::REASONS as $x)<option value="{{ $x }}" @selected($x === (($prefill && $prefill->age < 85) ? 'DISABLED' : 'AGE_85'))>{{ t('tr.reason.' . $x) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('tr.need') }}</label><select name="need" class="form-select form-select-sm">
                    @foreach(\App\Http\Controllers\TransportController::NEEDS as $x)<option value="{{ $x }}">{{ t('tr.need.' . $x) }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('tr.pickupTime') }}</label><input type="time" name="pickup_time" class="form-control form-control-sm"></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('tr.contact') }}</label><input name="mobile" class="form-control form-control-sm" maxlength="10" inputmode="tel" value="{{ $prefill->mobile ?? '' }}"></div>
                <div class="col-md-3"><label class="form-label small mb-0">{{ t('tr.notes') }}</label><input name="notes" class="form-control form-control-sm" maxlength="500"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
            </div>
        </div>
    </form>
    @elseif(!$boothNo)
    <div class="small text-muted mb-2">{{ t('tr.pickBooth') }}</div>
    @endif

    <div class="table-responsive bg-white border rounded"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>{{ t('col.name') }}</th><th>{{ t('col.boothNo') }}</th><th>{{ t('tr.reason') }}</th><th>{{ t('tr.pickup') }}</th><th>{{ t('tr.vehicle') }}</th><th>{{ t('tr.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $t)
        @php($v = $voters[$t->voter_id] ?? null)
        <tr>
            <td>@if($v)<a href="{{ url('/voters/' . $v->id) }}">{{ $v->name }}</a><div class="small text-muted">{{ t('col.age') }} {{ $v->age }} · {{ $v->epic_no }}</div>@else #{{ $t->voter_id }} @endif</td>
            <td>{{ $t->booth_no }}</td>
            <td class="small">{{ t('tr.reason.' . $t->reason) }}<br><span class="text-muted">{{ t('tr.need.' . $t->need) }}</span></td>
            <td class="small" style="max-width:16rem">{{ $t->pickup_time ? $t->pickup_time . ' · ' : '' }}{{ $t->pickup_address }}@if($t->mobile)<br><a href="tel:{{ $t->mobile }}">{{ $t->mobile }}</a>@endif @if($t->notes)<br><span class="text-muted">{{ $t->notes }}</span>@endif</td>
            <td style="min-width:15rem">
                <form method="post" action="{{ url('/transport/' . $t->id) }}" class="d-flex flex-wrap gap-1">@csrf
                    <input name="vehicle" value="{{ $t->vehicle }}" class="form-control form-control-sm" style="max-width:7rem" placeholder="{{ t('tr.vehicleNo') }}" maxlength="60">
                    <input name="driver_name" value="{{ $t->driver_name }}" class="form-control form-control-sm" style="max-width:7rem" placeholder="{{ t('tr.driver') }}" maxlength="80">
                    <input name="driver_mobile" value="{{ $t->driver_mobile }}" class="form-control form-control-sm" style="max-width:7rem" placeholder="{{ t('tr.driverMobile') }}" maxlength="10" inputmode="tel">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i></button>
                </form>
            </td>
            <td><span class="badge {{ $stCls[$t->status] }}">{{ t('tr.st.' . $t->status) }}</span></td>
            <td class="text-end text-nowrap">
                @foreach(['PICKED' => 'bi-car-front', 'VOTED' => 'bi-check2-circle', 'CANCELLED' => 'bi-x-lg'] as $s => $ic)
                @if($t->status !== $s && !in_array($t->status, ['VOTED', 'CANCELLED']))
                <form method="post" action="{{ url('/transport/' . $t->id) }}" class="d-inline">@csrf<input type="hidden" name="status" value="{{ $s }}">
                    <button class="btn btn-sm btn-outline-{{ $s === 'VOTED' ? 'success' : ($s === 'CANCELLED' ? 'danger' : 'secondary') }}" title="{{ t('tr.st.' . $s) }}"><i class="bi {{ $ic }}"></i></button></form>
                @endif
                @endforeach
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-4">{{ t('tr.none') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
