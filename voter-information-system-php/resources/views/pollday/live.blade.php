@extends('layouts.app')
@section('title', t('poll.live'))
@push('head')<meta http-equiv="refresh" content="60">@endpush
@section('content')
<div class="container-fluid px-3" style="max-width:1400px">
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-broadcast text-danger"></i> {{ t('poll.live') }}</h4>
            <div class="small text-muted">{{ $e?->name }} · {{ t('poll.refreshed', now()->format('H:i')) }}</div></div>
        <form method="get" class="d-flex gap-2 align-items-end">
            @unless($me->scoped())<div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>@endunless
            @if($wards)<div><label class="form-label small mb-0">{{ t('col.wardNo') }}</label><select name="wardNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($wards as $w)<option value="{{ $w }}" @selected($w == $wardNo)>{{ $w }}</option>@endforeach</select></div>@endif
        </form>
        <a class="btn btn-sm btn-outline-primary" href="{{ url('/pollday') }}?assemblyNo={{ $assemblyNo }}"><i class="bi bi-check2-square"></i> {{ t('poll.title') }}</a>
    </div>
    @if(!$e)<div class="alert alert-warning">{{ t('poll.noElection') }}</div>@else
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat"><div class="small text-muted">{{ t('dash.voters') }}</div><div class="num">{{ $tot['n'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="small text-muted">{{ t('poll.turnout') }}</div><div class="num">{{ $tot['voted'] }} ({{ $tot['n'] ? round(100 * $tot['voted'] / $tot['n'], 1) : 0 }}%)</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="small text-muted">{{ t('poll.supportTurnout') }}</div><div class="num">{{ $tot['supportVoted'] }} / {{ $tot['support'] }} ({{ $tot['support'] ? round(100 * $tot['supportVoted'] / $tot['support'], 1) : 0 }}%)</div></div></div>
        <div class="col-6 col-md-3"><div class="stat stat-link position-relative"><div class="small text-muted">{{ t('poll.pendingSupporters') }}</div>
            <div class="num text-danger"><a class="stretched-link text-decoration-none text-danger" href="{{ url('/voters') }}?{{ http_build_query(array_filter(['assemblyNo' => $assemblyNo, 'wardNo' => $wardNo, 'sentiment' => 'S', 'voted' => 'no', 'deleted' => 'exclude'], fn($v) => $v !== null)) }}">{{ $tot['support'] - $tot['supportVoted'] }}</a></div></div></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-lg-8"><div class="card h-100"><div class="card-body"><h6>{{ t('poll.byBooth') }}</h6><div class="chart-box" style="height:260px"><canvas data-chart="{{ json_encode($chart) }}" data-empty="{{ t('poll.noneYet') }}"></canvas></div></div></div></div>
        <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h6>{{ t('poll.votesPerHour') }}</h6><div class="chart-box" style="height:260px"><canvas data-chart="{{ json_encode($hourChart) }}" data-empty="{{ t('poll.noneYet') }}"></canvas></div></div></div></div>
    </div>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>{{ t('col.boothNo') }}</th><th>{{ t('col.boothName') }}</th><th class="text-end">{{ t('dash.voters') }}</th><th class="text-end">{{ t('poll.votedCol') }}</th>
            <th style="min-width:150px">{{ t('poll.turnout') }}</th><th class="text-end">{{ t('an.support') }}</th><th class="text-end">{{ t('poll.supportVoted') }}</th><th class="text-end">{{ t('poll.pendingSupporters') }}</th><th>{{ t('poll.lastMark') }}</th></tr></thead>
        <tbody>@foreach($rows as $x) @php($p = $x['n'] ? round(100 * $x['voted'] / $x['n']) : 0)
            <tr><td>{{ $x['b'] }}</td><td class="small">{{ $x['name'] }}</td><td class="text-end">{{ $x['n'] }}</td><td class="text-end">{{ $x['voted'] }}</td>
                <td><div class="d-flex align-items-center gap-2"><div class="progress flex-fill" style="height:8px"><div class="progress-bar" style="width:{{ $p }}%"></div></div><span class="small">{{ $p }}%</span></div></td>
                <td class="text-end">{{ $x['support'] }}</td><td class="text-end">{{ $x['supportVoted'] }}</td>
                <td class="text-end">@if($x['support'] - $x['supportVoted'] > 0)<a class="text-danger fw-semibold" href="{{ url('/voters') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $x['b'], 'sentiment' => 'S', 'voted' => 'no', 'deleted' => 'exclude']) }}">{{ $x['support'] - $x['supportVoted'] }}</a>@else 0 @endif</td>
                <td class="small text-muted">{{ $x['last'] ? substr((string) $x['last'], 11, 5) : '-' }}</td></tr>
        @endforeach</tbody>
        <tfoot class="table-light fw-semibold"><tr><td colspan="2">{{ t('report.total') }}</td><td class="text-end">{{ $tot['n'] }}</td><td class="text-end">{{ $tot['voted'] }}</td><td>{{ $tot['n'] ? round(100 * $tot['voted'] / $tot['n'], 1) : 0 }}%</td>
            <td class="text-end">{{ $tot['support'] }}</td><td class="text-end">{{ $tot['supportVoted'] }}</td><td class="text-end">{{ $tot['support'] - $tot['supportVoted'] }}</td><td></td></tr></tfoot>
    </table></div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ versioned_asset('js/analytics.js') }}"></script>
@endpush
