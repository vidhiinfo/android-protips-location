@extends('layouts.app')
@section('title', t('task.my'))
@section('content')
<div class="container px-3" style="max-width:760px">
    @include('partials.flash')
    <div class="d-flex align-items-center gap-2 mb-1">
        <h4 class="mb-0 me-auto"><i class="bi bi-list-check"></i> {{ t('task.my') }}</h4>
        <a href="{{ url('/my/attendance') }}" class="btn btn-sm {{ $att ? 'btn-outline-success' : 'btn-success' }}"><i class="bi bi-calendar-check"></i>
            {{ !$att ? t('att.checkIn') : ($att->check_out ? t('att.title') : t('att.checkOut')) }}</a>
    </div>
    <p class="small text-muted">{{ t('task.myIntro') }}</p>
    @forelse($tasks as $tk)
    @php($p = $progress[$tk->id])
    @php($late = $tk->status === 'OPEN' && $tk->due_date && $tk->due_date < $today)
    <div class="card mb-2 {{ $late ? 'border-danger' : '' }}"><div class="card-body py-2">
        <div class="d-flex gap-2 align-items-start">
            <div class="me-auto"><div class="fw-semibold">{{ $tk->title }}</div>
                <div class="small text-muted">{{ t('task.kind.' . $tk->kind) }}@if($tk->booth_no) · {{ t('col.boothNo') }} {{ $tk->booth_no }}@endif
                    @if($tk->due_date) · {{ t('task.due') }} <span class="{{ $late ? 'text-danger fw-semibold' : '' }}">{{ $tk->due_date }}</span>@endif</div>
                @if($tk->notes)<div class="small">{{ $tk->notes }}</div>@endif</div>
            @if($tk->status === 'DONE')<span class="badge text-bg-success">{{ t('task.status.DONE') }}</span>
            @elseif($late)<span class="badge text-bg-danger">{{ t('task.overdue') }}</span>
            @elseif($tk->due_date === $today)<span class="badge text-bg-warning">{{ t('task.dueToday') }}</span>@endif
        </div>
        <div class="small mt-1">{{ t('task.progress') }}: {{ $p['done'] }}{{ $tk->target ? ' / ' . $tk->target : '' }}</div>
        @if($p['pct'] !== null)<div class="progress mb-2" style="height:6px"><div class="progress-bar {{ $p['pct'] >= 100 ? 'bg-success' : '' }}" style="width:{{ $p['pct'] }}%"></div></div>@endif
        @if($tk->status === 'OPEN')
        <form method="post" action="{{ url('/my/tasks/' . $tk->id) }}" class="d-flex gap-2 mt-1">@csrf
            @if($tk->kind === 'SURVEY')<a href="{{ url('/survey') }}?{{ http_build_query(['assemblyNo' => $tk->assembly_no, 'boothNo' => $tk->booth_no]) }}" class="btn btn-sm btn-primary me-auto"><i class="bi bi-clipboard2-check"></i> {{ t('fs.title') }}</a>
            @else<input name="done_count" type="number" min="0" class="form-control form-control-sm" style="max-width:8rem" value="{{ $tk->done_count }}" placeholder="{{ t('task.howMany') }}">
                <button class="btn btn-sm btn-outline-primary me-auto">{{ t('task.updateCount') }}</button>@endif
            <button name="status" value="DONE" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> {{ t('task.markDone') }}</button>
        </form>
        @endif
    </div></div>
    @empty
    <div class="alert alert-light border">{{ t('task.none') }}</div>
    @endforelse
</div>
@endsection
