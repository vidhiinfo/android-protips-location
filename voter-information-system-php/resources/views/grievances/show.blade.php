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
        <span class="small text-muted ms-2">{{ t('gr.loggedBy', $g->created_at?->format('d-m-Y H:i'), $g->created_by) }} · {{ t('gr.daysOpen', $g->ageDays()) }}</span></div>
    <div class="row g-3">
        <div class="col-md-7"><div class="card h-100"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ t('gr.complainant') }}</dt><dd class="col-sm-8">@if($g->voter_id)<a href="{{ url('/voters/'.$g->voter_id) }}">{{ $g->name }}</a>@else{{ $g->name }}@endif</dd>
                <dt class="col-sm-4">{{ t('col.mobile') }}</dt><dd class="col-sm-8">@if($g->mobile)<a href="tel:{{ $g->mobile }}">{{ $g->mobile }}</a>@else - @endif</dd>
                <dt class="col-sm-4">{{ t('col.boothNo') }}</dt><dd class="col-sm-8">{{ $g->assembly_no }} / {{ $g->booth_no ?: '-' }}@if($g->ward_no) · {{ t('col.wardNo') }} {{ $g->ward_no }}@endif</dd>
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
    @if($others->count())
    <h6 class="mt-4">{{ t('gr.otherFromVoter') }}</h6>
    <ul class="list-group">@foreach($others as $o)<li class="list-group-item d-flex"><a class="me-auto" href="{{ url('/grievances/'.$o->id) }}">#{{ $o->id }} {{ $o->subject }}</a>
        <span class="badge {{ $stCls[$o->status] }}">{{ t('gr.status.'.$o->status) }}</span></li>@endforeach</ul>
    @endif
</div>
@endsection
