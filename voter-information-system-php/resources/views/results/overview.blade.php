@extends('layouts.app')
@section('title', t('result.overview'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h4 class="me-auto mb-0"><i class="bi bi-trophy"></i> {{ t('result.overview') }}</h4>
        <form method="get" class="d-flex gap-2">@include('results._election-select')</form>
        @if(can('RESULT_ENTRY'))<a class="btn btn-sm btn-primary text-nowrap" href="{{ url('/results/entry') }}?electionId={{ $election?->id }}"><i class="bi bi-pencil-square"></i> {{ t('result.entry') }}</a>@endif
        @if(can('REPORT_VIEW'))<a class="btn btn-sm btn-outline-secondary text-nowrap" href="{{ url('/reports/results-summary') }}?electionId={{ $election?->id }}"><i class="bi bi-bar-chart"></i> {{ t('report.results-summary') }}</a>@endif
    </div>
    @if(!$contests)<div class="alert alert-info">{{ t('result.noContests') }}</div>@endif
    <div class="row g-3">
        @foreach($contests as $c)
        <div class="col-md-6 col-xl-4"><div class="card h-100 {{ $c['winner'] ? 'winner-card' : '' }}"><div class="card-body">
            <div class="d-flex justify-content-between"><h6 class="mb-1"><a href="{{ url('/results/contest') }}?electionId={{ $c['election']->id }}&assemblyNo={{ $c['assembly_no'] }}">{{ $c['assembly_no'] }} - {{ $c['assembly_name'] }}</a></h6>
                <span class="badge {{ $c['complete'] ? 'text-bg-success' : 'text-bg-warning' }}">{{ $c['complete'] ? t('result.final') : t('result.counting') }}</span></div>
            <div class="small text-muted mb-2">{{ t('result.boothsReported') }}: {{ $c['booths_reported'] }}/{{ $c['booths_total'] }}</div>
            @foreach(array_slice($c['totals'], 0, 3) as $tt)
            <div class="mb-2"><div class="d-flex justify-content-between small"><span><b>{{ $tt['candidate']->partyShort() }}</b> {{ $tt['candidate']->name }}</span>
                <span>{{ $tt['votes'] }} ({{ number_format($tt['share'], 1) }}%)</span></div>
                <div class="vote-bar"><span style="width:{{ $tt['share'] }}%;background:{{ $tt['candidate']->partyColor() }}"></span></div></div>
            @endforeach
            @if($c['winner'])<div class="small">{{ $c['complete'] ? t('result.won') : t('result.leading') }}: <b>{{ $c['winner']['candidate']->name }}</b> &middot; {{ t('result.margin') }} <b>{{ $c['margin'] }}</b></div>@endif
        </div></div></div>
        @endforeach
    </div>
</div>
@endsection
