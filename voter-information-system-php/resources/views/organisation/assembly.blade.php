@extends('layouts.app')
@section('title', t('org.title') . ' - ' . $name)
@section('content')
@php($leaders = $data['incharge']->concat(collect($data['mandals'])->flatMap(fn ($m) => $m['head']))->concat(collect($data['booths'])->flatMap(fn ($b) => $b['president'])))
@php($accessBtn = function ($w) use ($me) { return can('USER_ADMIN') && $w->username
    ? '<form method="post" action="' . url('/organisation/person/' . $w->id . '/access') . '" class="d-inline">' . csrf_field() . '<button class="btn btn-link btn-sm p-0 ms-1" title="' . e(t('org.setAccessHint')) . '"><i class="bi bi-key"></i></button></form>' : ''; })
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><a href="{{ url('/organisation') }}" class="small"><i class="bi bi-arrow-left"></i> {{ t('org.title') }}</a>
            <h4 class="mb-0"><i class="bi bi-diagram-3"></i> {{ $a }} - {{ $name }}</h4></div>
        <a class="btn btn-sm btn-primary" href="{{ url('/workers/new') }}?assemblyNo={{ $a }}"><i class="bi bi-plus-lg"></i> {{ t('worker.add') }}</a>
    </div>

    <div class="card mb-3"><div class="card-body py-2">
        <span class="fw-semibold">{{ role_label('AC_INCHARGE') }}:</span>
        @forelse($data['incharge'] as $h)<a href="{{ url('/workers/' . $h->id) }}" class="ms-1">{{ $h->name }}</a>@if($h->mobile) <span class="small text-muted">{{ $h->mobile }}</span>@endif{!! $accessBtn($h) !!}{{ !$loop->last ? ',' : '' }}
        @empty<span class="text-danger small ms-1"><i class="bi bi-exclamation-circle"></i> {{ t('org.missing') }}</span><a class="small ms-1 text-nowrap" href="{{ url('/workers/new') }}?assemblyNo={{ $a }}&role=AC_INCHARGE"><i class="bi bi-plus-circle"></i> {{ t('org.addHere') }}</a>@endforelse
    </div></div>

    {{-- mandals --}}
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-collection"></i> {{ t('ls.mandals') }} <span class="badge text-bg-primary">{{ count($data['mandals']) }}</span></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>{{ t('org.mandal') }}</th><th>{{ role_label('MANDAL_INCHARGE') }}</th><th>{{ t('ls.booths') }}</th><th class="text-end">{{ t('dash.voters') }}</th>
                <th class="text-end">{{ t('org.noPresident') }}</th><th class="text-end">{{ t('org.tasksDone') }}</th><th></th></tr></thead>
            <tbody>@forelse($data['mandals'] as $m)
                <tr>
                    <td class="fw-semibold">{{ $m['mandal']->name }}</td>
                    <td>@forelse($m['head'] as $h)<a href="{{ url('/workers/' . $h->id) }}">{{ $h->name }}</a>{!! $accessBtn($h) !!}{{ !$loop->last ? ', ' : '' }}@empty<span class="text-danger small"><i class="bi bi-exclamation-circle"></i> {{ t('org.missing') }}</span><a class="small ms-1 text-nowrap" href="{{ url('/workers/new') }}?assemblyNo={{ $a }}&role=MANDAL_INCHARGE&mandal={{ $m['mandal']->id }}"><i class="bi bi-plus-circle"></i> {{ t('org.addHere') }}</a>@endforelse</td>
                    <td class="small">{{ implode(', ', $m['booths']) ?: '-' }}</td><td class="text-end">{{ number_format($m['voters']) }}</td>
                    <td class="text-end">@if($m['noPresident'])<span class="text-danger">{{ $m['noPresident'] }}</span>@else 0 @endif</td>
                    <td class="text-end">{{ $m['tasks']['total'] ? $m['tasks']['done'] . '/' . $m['tasks']['total'] : '-' }}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#m-{{ $m['mandal']->id }}"><i class="bi bi-pencil"></i></button>
                        <form method="post" action="{{ url('/organisation/' . $a . '/mandals/' . $m['mandal']->id . '/delete') }}" class="d-inline" data-confirm="{{ t('common.confirmDelete') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
                <tr class="collapse" id="m-{{ $m['mandal']->id }}"><td colspan="7">
                    <form method="post" action="{{ url('/organisation/' . $a . '/mandals') }}" class="row g-2 align-items-end">@csrf<input type="hidden" name="id" value="{{ $m['mandal']->id }}">
                        <div class="col-md-3"><label class="form-label small mb-0">{{ t('org.mandalName') }}</label><input name="name" class="form-control form-control-sm" value="{{ $m['mandal']->name }}" required maxlength="100"></div>
                        <div class="col-md-7"><label class="form-label small mb-0">{{ t('org.mandalBooths') }}</label><input name="booths" class="form-control form-control-sm" value="{{ $m['mandal']->booths }}" placeholder="1-20, 25, 31"></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ t('common.save') }}</button></div>
                    </form></td></tr>
            @empty<tr><td colspan="7" class="text-muted small">{{ t('org.noMandals') }}</td></tr>@endforelse</tbody>
        </table></div>
        <div class="card-body border-top">
            <form method="post" action="{{ url('/organisation/' . $a . '/mandals') }}" class="row g-2 align-items-end">@csrf
                <div class="col-md-3"><label class="form-label small mb-0">{{ t('org.mandalName') }}</label><input name="name" class="form-control form-control-sm" required maxlength="100"></div>
                <div class="col-md-7"><label class="form-label small mb-0">{{ t('org.mandalBooths') }}</label><input name="booths" class="form-control form-control-sm" placeholder="1-20, 25, 31"><div class="form-text">{{ t('org.unplaced', count($data['unplaced'])) }}@if($data['unplaced']): {{ implode(', ', array_slice($data['unplaced'], 0, 40)) }}{{ count($data['unplaced']) > 40 ? ' ...' : '' }}@endif</div></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> {{ t('org.addMandal') }}</button></div>
            </form>
        </div>
    </div>

    {{-- task for a whole team --}}
    @if($leaders->isNotEmpty())
    <div class="card mb-3"><div class="card-header fw-semibold"><i class="bi bi-list-check"></i> {{ t('org.teamTask') }}</div><div class="card-body">
        <form method="post" action="{{ url('/organisation/team-task') }}" class="row g-2 align-items-end">@csrf
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('org.leader') }}</label><select name="leader_id" class="form-select form-select-sm" required><option value="">--</option>
                @foreach($leaders as $l)<option value="{{ $l->id }}">{{ $l->name }} ({{ role_label($l->role) }}{{ $l->booth_no ? ', ' . t('col.boothNo') . ' ' . $l->booth_no : '' }})</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('org.taskTitle') }}</label><input name="title" class="form-control form-control-sm" required maxlength="200"></div>
            <div class="col-md-2"><label class="form-label small mb-0">{{ t('task.kind') }}</label><select name="kind" class="form-select form-select-sm">@foreach($kinds as $k)<option value="{{ $k }}">{{ t('task.kind.' . $k) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small mb-0">{{ t('task.due') }}</label><input type="date" name="due_date" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ t('org.giveTask') }}</button></div>
            <div class="col-12 small">{{ t('org.onlyRoles') }}
                @foreach(array_filter(\App\Support\WorkerRoles::codes(true), fn ($c) => !in_array(\App\Support\WorkerRoles::base($c), ['LS_INCHARGE', 'AC_INCHARGE'], true)) as $ro)
                <label class="ms-2"><input type="checkbox" name="roles[]" value="{{ $ro }}"> {{ role_label($ro) }}</label>@endforeach</div>
        </form>
    </div></div>
    @endif

    {{-- booths --}}
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-geo-alt"></i> {{ t('ls.booths') }} <span class="badge text-bg-primary">{{ count($data['booths']) }}</span></div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>{{ t('col.boothNo') }}</th><th>{{ t('org.mandal') }}</th><th>{{ role_label('BOOTH_PRESIDENT') }}</th><th class="text-end">{{ t('org.agents') }}</th>
                <th class="text-end">{{ t('org.pannaCount') }}</th><th class="text-end">{{ t('dash.voters') }}</th><th class="text-end">{{ t('ls.panna') }}</th></tr></thead>
            <tbody>@foreach($data['booths'] as $b)
                <tr>
                    <td><a href="{{ url('/workers') }}?assemblyNo={{ $a }}&boothNo={{ $b['booth'] }}">{{ $b['booth'] }}</a></td>
                    <td class="small">{{ $b['mandal']?->name ?? '-' }}</td>
                    <td>@forelse($b['president'] as $p)<a href="{{ url('/workers/' . $p->id) }}">{{ $p->name }}</a>{!! $accessBtn($p) !!}{{ !$loop->last ? ', ' : '' }}@empty<span class="text-danger small"><i class="bi bi-exclamation-circle"></i> {{ t('org.missing') }}</span><a class="small ms-1 text-nowrap" href="{{ url('/workers/new') }}?assemblyNo={{ $a }}&role=BOOTH_PRESIDENT&boothNo={{ $b['booth'] }}"><i class="bi bi-plus-circle"></i> {{ t('org.addHere') }}</a>@endforelse</td>
                    <td class="text-end">{{ $b['agents']->count() }}<a class="small ms-1" title="{{ role_label('BOOTH_AGENT') }} text-nowrap" href="{{ url('/workers/new') }}?assemblyNo={{ $a }}&role=BOOTH_AGENT&boothNo={{ $b['booth'] }}"><i class="bi bi-plus-circle"></i></a></td>
                    <td class="text-end">@if($b['panna']->isEmpty())<span class="text-danger">0</span><a class="small ms-1 text-nowrap" href="{{ url('/workers/new') }}?assemblyNo={{ $a }}&role=PANNA_PRAMUKH&boothNo={{ $b['booth'] }}"><i class="bi bi-plus-circle"></i> {{ t('org.addHere') }}</a>@else{{ $b['panna']->count() }}@endif</td>
                    <td class="text-end">{{ number_format($b['voters']) }}</td>
                    <td class="text-end">{{ $b['voters'] ? round(100 * $b['pannaVoters'] / $b['voters']) . '%' : '-' }}</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    </div>
    @if(can('USER_ADMIN'))<p class="small text-muted"><i class="bi bi-key"></i> {{ t('org.setAccessHint') }}</p>@endif
</div>
@endsection
