@extends('layouts.app')
@section('title', t('dash.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1400px" id="dashboard" data-url="{{ url('/dashboard/data') }}">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-speedometer2"></i> {{ t('dash.title') }}</h4><div class="small text-muted">{{ t('dash.subtitle') }}</div></div>
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
            <div><label class="form-label small mb-0">{{ t('master.election') }}</label>
                <select name="electionId" class="form-select form-select-sm" data-autosubmit>
                    @foreach($elections as $e)<option value="{{ $e->id }}" @selected($election && $e->id == $election->id)>{{ $e->name }}</option>@endforeach
                </select></div>
            @if($data && $data['election'])
            <div><label class="form-label small mb-0">{{ t('dash.focusParty') }}</label>
                <select name="party" class="form-select form-select-sm" data-autosubmit>
                    @foreach($data['election']['parties'] as $p)<option value="{{ $p }}" @selected($p === $data['election']['focusParty'])>{{ $p }}</option>@endforeach
                </select></div>
            @endif
            <button type="button" class="btn btn-sm btn-secondary" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
        </form>
    </div>
    @if(!$data)
        <div class="alert alert-info">{{ t('dash.pickAssembly') }}</div>
    @else
        <div class="row g-2 mb-3">
            @foreach($data['tiles'] as $tile)
            <div class="col-6 col-md-4 col-xl-2"><div class="stat h-100"><div class="text-muted small">{{ $tile['label'] }}</div><div class="num fs-5">{{ $tile['value'] }}</div></div></div>
            @endforeach
        </div>
        <div class="row g-3 mb-3">
            @include('partials.chart-card', ['id' => 'chartAgeGender', 'key' => 'ageGender'])
            @include('partials.chart-card', ['id' => 'chartBoothVoters', 'key' => 'boothVoters'])
        </div>
        @if(!$data['election'])<div class="alert alert-light border">{{ t('dash.noElection') }}</div>
        @else
        <div class="row g-3 mb-3">
            @include('partials.chart-card', ['id' => 'chartMargin', 'key' => 'margin'])
            @include('partials.chart-card', ['id' => 'chartPartyShare', 'key' => 'partyShare'])
            @include('partials.chart-card', ['id' => 'chartTurnout', 'key' => 'turnout'])
            @include('partials.chart-card', ['id' => 'chartMobile', 'key' => 'mobile'])
        </div>
        @endif
        <div class="row g-3 mb-4">
            @if(!$data['election'])@include('partials.chart-card', ['id' => 'chartMobile', 'key' => 'mobile'])@endif
            @include('partials.chart-card', ['id' => 'chartYoung', 'key' => 'young'])
            @include('partials.chart-card', ['id' => 'chartHousehold', 'key' => 'household'])
        </div>
    @endif
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
