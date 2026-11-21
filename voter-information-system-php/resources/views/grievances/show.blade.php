@extends('layouts.app')
@section('title', t('gr.title') . ' #' . $g->id)
@section('content')
@php($stCls = ['OPEN' => 'text-bg-danger', 'IN_PROGRESS' => 'text-bg-warning', 'RESOLVED' => 'text-bg-success', 'CLOSED' => 'text-bg-secondary'])
@php($prCls = ['URGENT' => 'text-bg-danger', 'HIGH' => 'text-bg-warning', 'NORMAL' => 'text-bg-light border', 'LOW' => 'text-bg-light border text-muted'])
<div class="container" style="max-width:900px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <a href="{{ url('/grievances') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h4 class="mb-0 me-auto">#{{ $g->id }} {{ $g->subject }}</h4>
        <a class="btn btn-sm btn-outline-primary" href="{{ url('/grievances/'.$g->id.'/edit') }}"><i class="bi bi-pencil"></i> {{ t('common.edit') }}</a>
    </div>
    <div class="mb-3"><span class="badge {{ $stCls[$g->status] }}">{{ t('gr.status.'.$g->status) }}</span> <span class="badge {{ $prCls[$g->priority] }}">{{ t('gr.pr.'.$g->priority) }}</span>
        <span class="badge text-bg-light border">{{ t('gr.cat.'.$g->category) }}</span>
        <span class="small text-muted ms-2">{{ t('gr.loggedBy', $g->created_at?->format('d-m-Y H:i'), $g->created_by) }} · {{ t('gr.daysOpen', $g->ageDays()) }}</span>
        @if($g->due_at)<span class="badge {{ $g->overdue() ? 'text-bg-danger' : 'text-bg-light border' }} ms-1"><i class="bi bi-alarm"></i> {{ $g->overdue() ? t('grv.overdueSince', $g->due_at->format('d-m-Y')) : t('grv.dueOn', $g->due_at->format('d-m-Y')) }}</span>@endif
        @if($g->escalation_level)<span class="badge text-bg-dark ms-1"><i class="bi bi-arrow-up-circle"></i> {{ t('grv.level', $g->escalation_level) }}</span>@endif</div>
    <div class="row g-3">
        <div class="col-md-7"><div class="card h-100"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ t('gr.complainant') }}</dt><dd class="col-sm-8">@if($g->voter_id)<a href="{{ url('/voters/'.$g->voter_id) }}">{{ $g->name }}</a>@else{{ $g->name }}@endif</dd>
                <dt class="col-sm-4">{{ t('col.mobile') }}</dt><dd class="col-sm-8">@if($g->mobile)<a href="tel:{{ $g->mobile }}">{{ $g->mobile }}</a>@else - @endif</dd>
                <dt class="col-sm-4">{{ t('col.boothNo') }}</dt><dd class="col-sm-8">{{ $g->assembly_no }} / {{ $g->booth_no ?: '-' }}@if($g->ward_no && \App\Models\Voter::showWard()) · {{ t('col.wardNo') }} {{ $g->ward_no }}@endif</dd>
                <dt class="col-sm-4">{{ t('gr.location') }}</dt><dd class="col-sm-8">{{ $g->location ?: '-' }}</dd>
                <dt class="col-sm-4">{{ t('gr.assigned') }}</dt><dd class="col-sm-8">@if($g->worker)<a href="{{ url('/workers/'.$g->worker_id) }}">{{ $g->worker->name }}</a>@else - @endif</dd>
                <dt class="col-sm-4">{{ t('gr.description') }}</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $g->description ?: '-' }}</dd>
                @if($g->resolution)<dt class="col-sm-4">{{ t('gr.resolution') }}</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $g->resolution }}</dd>@endif
                @if($g->resolved_at)<dt class="col-sm-4">{{ t('gr.resolvedOn') }}</dt><dd class="col-sm-8">{{ $g->resolved_at->format('d-m-Y H:i') }}</dd>@endif
            </dl></div></div></div>
        <div class="col-md-5"><form method="post" action="{{ url('/grievances/'.$g->id.'/status') }}" class="card card-body h-100">@csrf
            <div class="fw-semibold mb-2">{{ t('gr.update') }}</div>
            <textarea name="resolution" class="form-control mb-2" rows="3" maxlength="1000" placeholder="{{ t('gr.resolutionHint') }}"></textarea>
            <div class="d-flex flex-wrap gap-2">
                @foreach(\App\Models\Grievance::STATUSES as $s) @if($s !== $g->status)
                <button name="status" value="{{ $s }}" class="btn btn-sm {{ $s === 'RESOLVED' ? 'btn-success' : 'btn-outline-secondary' }}">{{ t('gr.status.'.$s) }}</button>
                @endif @endforeach</div>
        </form></div>
    </div>
    @if($sla)
    <div class="row g-3 mt-1">
        <div class="col-md-7">
            <h6><i class="bi bi-clock-history"></i> {{ t('grv.timeline') }}</h6>
            <ul class="list-group mb-2">
                @forelse($updates as $u)
                <li class="list-group-item small d-flex gap-2"><span class="badge {{ ['ESCALATED' => 'text-bg-danger', 'STATUS' => 'text-bg-primary', 'PHOTO' => 'text-bg-info', 'NOTE' => 'text-bg-light border'][$u->kind] ?? 'text-bg-light border' }} align-self-start">{{ t('grv.kind.' . $u->kind) }}</span>
                    <span class="me-auto" dir="auto" style="white-space:pre-line">{{ $u->text }}</span><span class="text-muted text-nowrap">{{ substr($u->at, 0, 16) }}<br>{{ $u->by_user }}</span></li>
                @empty
                <li class="list-group-item small text-muted">{{ t('grv.noUpdates') }}</li>
                @endforelse
            </ul>
            <form method="post" action="{{ url('/grievances/' . $g->id . '/note') }}" class="d-flex gap-2">@csrf
                <input name="text" class="form-control form-control-sm" maxlength="1000" dir="auto" placeholder="{{ t('grv.notePh') }}" required>
                <button class="btn btn-sm btn-outline-primary text-nowrap">{{ t('grv.addNote') }}</button></form>
        </div>
        <div class="col-md-5">
            <h6><i class="bi bi-images"></i> {{ t('grv.photos') }}</h6>
            <div class="row g-2 mb-2">
                @foreach($photos as $p)
                <div class="col-6"><div class="card">
                    <a href="{{ url('/grievances/' . $g->id . '/photos/' . $p->id) }}" target="_blank"><img src="{{ url('/grievances/' . $g->id . '/photos/' . $p->id) }}" class="card-img-top" style="height:120px;object-fit:cover" alt="" loading="lazy"></a>
                    <div class="card-body p-1 small d-flex gap-1 align-items-center"><span class="badge {{ $p->stage === 'AFTER' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ t('grv.stage.' . $p->stage) }}</span>
                        <span class="text-truncate me-auto" title="{{ $p->caption }}">{{ $p->caption }}</span>
                        <form method="post" action="{{ url('/grievances/' . $g->id . '/photos/' . $p->id . '/delete') }}" data-confirm="{{ t('grv.photoDeleteConfirm') }}">@csrf<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form></div>
                </div></div>
                @endforeach
            </div>
            @if($photos->count() < \App\Services\Grievances\GrievanceService::MAX_PHOTOS)
            <form method="post" action="{{ url('/grievances/' . $g->id . '/photos') }}" enctype="multipart/form-data" class="card card-body py-2">@csrf
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple class="form-control form-control-sm mb-2" required>
                <div class="d-flex gap-2">
                    <select name="stage" class="form-select form-select-sm" style="max-width:9rem"><option value="BEFORE">{{ t('grv.stage.BEFORE') }}</option><option value="AFTER" @selected(!$g->isOpen())>{{ t('grv.stage.AFTER') }}</option></select>
                    <input name="caption" class="form-control form-control-sm" maxlength="200" placeholder="{{ t('grv.caption') }}">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-upload"></i></button></div>
                <div class="small text-muted mt-1">{{ t('grv.photoHint') }}</div>
            </form>
            @endif
        </div>
    </div>
    @endif
    @if($others->count())
    <h6 class="mt-4">{{ t('gr.otherFromVoter') }}</h6>
    <ul class="list-group">@foreach($others as $o)<li class="list-group-item d-flex"><a class="me-auto" href="{{ url('/grievances/'.$o->id) }}">#{{ $o->id }} {{ $o->subject }}</a>
        <span class="badge {{ $stCls[$o->status] }}">{{ t('gr.status.'.$o->status) }}</span></li>@endforeach</ul>
    @endif
</div>
@endsection
