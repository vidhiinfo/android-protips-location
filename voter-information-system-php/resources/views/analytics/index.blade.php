@extends('layouts.app')
@section('title', t('an.home'))
@section('content')
@php($icons = ['booth-scorecard' => 'trophy', 'voter-sentiment' => 'emoji-smile', 'survey-coverage' => 'clipboard-check', 'caste-booth' => 'grid-3x3',
    'beneficiaries' => 'gift', 'worker-coverage' => 'people', 'household-insights' => 'house', 'origin' => 'geo-alt', 'turnout-profile' => 'graph-up',
    'campaign-reach' => 'megaphone', 'team-activity' => 'person-check'])
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-graph-up-arrow"></i> {{ t('an.home') }}</h4><div class="small text-muted">{{ t('an.subtitle') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end no-print">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label>
                <select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                    @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach
                </select></div>
            @endunless
            @if($wards)
            <div><label class="form-label small mb-0">{{ t('col.wardNo') }}</label>
                <select name="wardNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                    @foreach($wards as $w)<option value="{{ $w }}" @selected($w == $wardNo)>{{ $w }}</option>@endforeach
                </select></div>
            @endif
            <button type="button" class="btn btn-sm btn-secondary" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
        </form>
    </div>
    @if(!$data)
        <div class="alert alert-info">{{ t('dash.pickAssembly') }}</div>
    @else
    <div class="row g-2 mb-3">
        @foreach($data['tiles'] as [$label, $value, $url])
        <div class="col-6 col-md-3"><div class="stat h-100 position-relative {{ $url ? 'stat-link' : '' }}"><div class="text-muted small">{{ t($label) }}</div>
            <div class="num fs-5">@if($url)<a href="{{ $url }}" class="stretched-link text-decoration-none">{{ $value }}</a>@else{{ $value }}@endif</div></div></div>
        @endforeach
    </div>
    <div class="row g-3 mb-3">
        @foreach(['sentiment' => 'voter-sentiment', 'grades' => 'booth-scorecard', 'age' => null, 'benefits' => 'beneficiaries', 'coverage' => 'survey-coverage'] as $key => $rep)
        <div class="col-lg-{{ $key === 'coverage' ? 12 : 6 }}"><div class="card h-100"><div class="card-body">
            <div class="d-flex align-items-start gap-2"><div class="me-auto"><h6 class="mb-0">{{ t('an.chart.'.$key) }}</h6>
                <p class="small text-muted mb-2">{{ t('an.chart.'.$key.'.desc') }}</p></div>
                @if($rep && in_array($rep, $ids))<a class="btn btn-sm btn-outline-secondary no-print" href="{{ url('/reports/'.$rep) }}?{{ $query }}">{{ t('an.details') }}</a>@endif</div>
            <div class="chart-box" style="height:{{ !empty($data['charts'][$key]['horizontal']) ? max(240, 26 * count($data['charts'][$key]['labels'] ?? [])) : 260 }}px">
                <canvas data-chart="{{ json_encode($data['charts'][$key]) }}" data-empty="{{ t('an.noSurvey') }}" role="img" aria-label="{{ t('an.chart.'.$key) }}"></canvas></div>
        </div></div></div>
        @endforeach
    </div>
    @endif
    <h6 class="text-uppercase text-muted small mt-2 mb-2">{{ t('an.all') }}</h6>
    <div class="row g-3 mb-4">@foreach($ids as $id)
        <div class="col-md-6 col-lg-4"><a class="card h-100 text-decoration-none report-card" href="{{ url('/reports/'.$id) }}?{{ $query }}"><div class="card-body">
            <h6 class="card-title"><i class="bi bi-{{ $icons[$id] }} text-primary"></i> {{ t('report.'.$id) }}
                @if(in_array($id, \App\Services\Reports\ReportService::ADMIN_ONLY))<i class="bi bi-shield-lock text-warning" title="{{ t('report.adminOnly') }}"></i>@endif</h6>
            <p class="card-text small text-muted mb-0">{{ t('report.'.$id.'.desc') }}</p></div></a></div>
    @endforeach</div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ versioned_asset('js/analytics.js') }}"></script>
@endpush
