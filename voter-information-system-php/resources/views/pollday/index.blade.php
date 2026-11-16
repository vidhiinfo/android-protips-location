@extends('layouts.app')
@section('title', t('poll.title'))
@section('content')
<div class="container px-3" style="max-width:820px">
    <div class="d-flex align-items-center mb-1"><h4 class="mb-0 me-auto"><i class="bi bi-check2-square"></i> {{ t('poll.title') }}</h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/pollday/live') }}?assemblyNo={{ $assemblyNo }}"><i class="bi bi-broadcast"></i> {{ t('poll.live') }}</a></div>
    <p class="small text-muted">{{ $e ? $e->name . ($e->poll_date ? ' · ' . \Illuminate\Support\Carbon::parse($e->poll_date)->format('d-m-Y') : '') : '' }}</p>
    @include('partials.flash')
    @if(!$e)<div class="alert alert-warning">{{ t('poll.noElection') }}</div>@else
    @include('pollday._pick')
    @if($stats)
    <div class="row g-2 mb-3">
        <div class="col-6"><div class="stat"><div class="small text-muted">{{ t('poll.turnout') }}</div>
            <div class="num">{{ $stats['voted'] }} / {{ $stats['n'] }} <small class="text-muted">({{ $stats['n'] ? round(100 * $stats['voted'] / $stats['n'], 1) : 0 }}%)</small></div></div></div>
        <div class="col-6"><div class="stat stat-link position-relative"><div class="small text-muted">{{ t('poll.pendingSupporters') }}</div>
            <div class="num text-danger"><a class="stretched-link text-decoration-none text-danger" href="{{ url('/voters') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo, 'sentiment' => 'S', 'voted' => 'no', 'deleted' => 'exclude']) }}">{{ $stats['support'] - $stats['supportVoted'] }}</a>
                <small class="text-muted">/ {{ $stats['support'] }}</small></div></div></div>
    </div>
    <form method="post" action="{{ url('/pollday/mark') }}" class="card card-body mb-3">@csrf
        <input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}"><input type="hidden" name="boothNo" value="{{ $boothNo }}">
        <label class="form-label fw-semibold">{{ t('poll.enter') }}</label>
        <div class="input-group input-group-lg"><input name="q" class="form-control" inputmode="numeric" autocomplete="off" autofocus placeholder="{{ t('poll.enterHint') }}" required>
            <button class="btn btn-success"><i class="bi bi-check2"></i> {{ t('poll.markVoted') }}</button></div>
        <div class="form-text">{{ t('poll.enterHelp', $boothNo) }}</div>
    </form>
    <div class="card"><div class="card-header fw-semibold">{{ t('poll.recent') }}</div>
        <ul class="list-group list-group-flush">@forelse($recent as $m) @php($v = $names[$m->voter_id] ?? null)
            <li class="list-group-item d-flex align-items-center gap-2">
                <span class="badge text-bg-success">{{ $v?->serial_no }}</span><span class="me-auto">{{ $v?->name }}</span>
                <span class="small text-muted">{{ $m->marked_at->format('H:i') }} · {{ $m->marked_by }}</span>
                <form method="post" action="{{ url('/pollday/unmark') }}" data-confirm="{{ t('poll.unmarkConfirm') }}">@csrf
                    <input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}"><input type="hidden" name="boothNo" value="{{ $boothNo }}"><input type="hidden" name="voter_id" value="{{ $m->voter_id }}">
                    <button class="btn btn-sm btn-outline-secondary" title="{{ t('poll.undo') }}"><i class="bi bi-arrow-counterclockwise"></i></button></form>
            </li>
        @empty <li class="list-group-item text-muted">{{ t('poll.noneYet') }}</li> @endforelse</ul></div>
    @else <div class="alert alert-info">{{ t('poll.pickBooth') }}</div> @endif
    @endif
</div>
@endsection
