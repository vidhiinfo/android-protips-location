@extends('layouts.app')
@section('title', $w->name)
@section('content')
<div class="container" style="max-width:1000px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <a href="{{ url('/workers') }}?assemblyNo={{ $w->assembly_no }}&boothNo={{ $w->booth_no }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h4 class="mb-0 me-auto"><i class="bi bi-person-badge"></i> {{ $w->name }} <span class="badge text-bg-light border fs-6">{{ role_label($w->role) }}</span></h4>
        <a class="btn btn-sm btn-outline-primary" href="{{ url('/workers/'.$w->id.'/edit') }}"><i class="bi bi-pencil"></i> {{ t('common.edit') }}</a>
        <form method="post" action="{{ url('/workers/'.$w->id.'/delete') }}" data-confirm="{{ t('common.confirmDelete') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </div>
    <p class="text-muted small">{{ t('col.assemblyNo') }} {{ $w->assembly_no }}@if($w->booth_no) · {{ t('col.boothNo') }} {{ $w->booth_no }}@endif @if($w->mobile) · <a href="tel:{{ $w->mobile }}">{{ $w->mobile }}</a>@endif @if($w->username) · {{ t('worker.login') }}: <b>{{ $w->username }}</b>@endif
        @if($w->voter_id && ($wv = $w->voter)) · <i class="bi bi-person-vcard"></i> <a href="{{ url('/voters/' . $wv->id) }}">{{ t('worker.ownVoter') }}: {{ $wv->epic_no }}</a>@endif</p>
    <div class="row g-2 mb-3">
        @foreach([['dash.voters', $s['n']], ['an.households', $s['houses'] ?? 0], ['worker.surveyed', $s['surveyed'] . ($s['n'] ? ' (' . round(100 * $s['surveyed'] / $s['n']) . '%)' : '')], ['an.support', $s['support']], ['worker.voted', $s['voted']]] as [$k, $val])
        <div class="col-6 col-md"><div class="stat"><div class="small text-muted">{{ t($k) }}</div><div class="num">{{ $val }}</div></div></div>
        @endforeach
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm btn-primary" href="{{ url('/voters') }}?workerId={{ $w->id }}"><i class="bi bi-people"></i> {{ t('worker.openVoters') }}</a>
        @if(can('VOTER_EXPORT'))<a class="btn btn-sm btn-outline-danger" href="{{ url('/voters/export/slips') }}?workerId={{ $w->id }}"><i class="bi bi-file-earmark-pdf"></i> {{ t('slip.title') }}</a>
        <a class="btn btn-sm btn-outline-success" href="{{ url('/voters/export/survey') }}?workerId={{ $w->id }}"><i class="bi bi-clipboard-check"></i> {{ t('survey.sheet') }}</a>@endif
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/voters') }}?workerId={{ $w->id }}&sentiment=S&voted=no"><i class="bi bi-person-exclamation"></i> {{ t('poll.pendingSupporters') }}</a>
    </div>
    <div class="row g-3">
        <div class="col-md-6"><div class="card h-100"><div class="card-header fw-semibold">{{ t('worker.assignedRanges') }}</div>
            <ul class="list-group list-group-flush">@forelse($ranges as $rg)
                <li class="list-group-item d-flex"><span class="me-auto">{{ t('col.boothNo') }} {{ $rg->booth_no }}: {{ t('worker.serials', $rg->lo, $rg->hi) }}</span><span class="badge text-bg-primary">{{ $rg->n }}</span></li>
            @empty <li class="list-group-item text-muted">{{ t('worker.noVoters') }}</li> @endforelse</ul>
            @if($ranges->count())<div class="card-body pt-2"><form method="post" action="{{ url('/workers/'.$w->id.'/unassign') }}" data-confirm="{{ t('worker.unassignConfirm') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ t('worker.unassignAll') }}</button></form></div>@endif
        </div></div>
        <div class="col-md-6"><form method="post" action="{{ url('/workers/'.$w->id.'/assign') }}" class="card card-body h-100">@csrf
            <div class="fw-semibold mb-2">{{ t('worker.assignTitle') }}</div>
            <div class="row g-2">
                <div class="col-4"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="booth_no" class="form-select form-select-sm">
                    @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $w->booth_no)>{{ $b }}</option>@endforeach</select></div>
                <div class="col-4"><label class="form-label small mb-0">{{ t('worker.from') }}</label><input name="from" type="number" min="1" class="form-control form-control-sm" required></div>
                <div class="col-4"><label class="form-label small mb-0">{{ t('worker.to') }}</label><input name="to" type="number" min="1" class="form-control form-control-sm" required></div>
            </div>
            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="replace" value="1" id="rep2"><label class="form-check-label small" for="rep2">{{ t('worker.replace') }}</label></div>
            <div class="small text-muted">{{ t('worker.assignHint') }}</div>
            <div><button class="btn btn-sm btn-primary mt-2">{{ t('worker.assign') }}</button></div>
        </form></div>
    </div>
</div>
@endsection
