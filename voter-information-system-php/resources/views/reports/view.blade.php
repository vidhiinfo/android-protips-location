@extends('layouts.app')
@section('title', $report->title)
@section('content')
@php($q = http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo, 'electionId' => $electionId, 'scope' => $scope, 'party' => $party]))
@php($electionReport = in_array($reportId, ['results-summary', 'party-performance', 'booth-priority']))
<div class="container-fluid px-3" style="max-width:1400px">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h4 class="me-auto mb-0">{{ $report->title }}</h4>
        <a class="btn btn-sm btn-success" href="{{ url('/reports/'.$reportId.'/export/xlsx') }}?{{ $q }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a class="btn btn-sm btn-danger" href="{{ url('/reports/'.$reportId.'/export/pdf') }}?{{ $q }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a class="btn btn-sm btn-secondary" target="_blank" href="{{ url('/reports/'.$reportId) }}?{{ $q }}&print=1"><i class="bi bi-printer"></i> {{ t('common.print') }}</a>
    </div>
    <p class="text-muted small">{{ $report->subtitle }}</p>
    <form method="get" class="card card-body py-2 mb-3"><div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">{{ t('col.assemblyNo') }}</label>
            <select name="assemblyNo" class="form-select form-select-sm" @disabled($me->scoped())><option value="">{{ t('common.all') }}</option>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
        @if(in_array($reportId, ['age-gender', 'first-time-voters', 'senior-voters', 'phone-bank']))
        <div class="col-md-2"><label class="form-label small">{{ t('col.boothNo') }}</label>
            <select name="boothNo" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
        @endif
        @if($reportId === 'duplicate-voters')
        <div class="col-md-3"><label class="form-label small">{{ t('report.dup.match') }}</label>
            <select name="scope" class="form-select form-select-sm"><option value="assembly">{{ t('report.dup.byAssembly') }}</option>
                <option value="ward" @selected($scope === 'ward')>{{ t('report.dup.byWard') }}</option></select></div>
        @endif
        @if($reportId === 'booth-priority')
        <div class="col-md-2"><label class="form-label small">{{ t('dash.focusParty') }}</label>
            <select name="party" class="form-select form-select-sm"><option value="">{{ t('report.seatWinner') }}</option>
                @foreach($parties as $p)<option value="{{ $p->short_name }}" @selected($p->short_name === $party)>{{ $p->short_name }}</option>@endforeach</select></div>
        @endif
        @if($electionReport)
        <div class="col-md-4"><label class="form-label small">{{ t('master.election') }}</label>
            <select name="electionId" class="form-select form-select-sm">
                @foreach($elections as $e)<option value="{{ $e->id }}" @selected($e->id == $electionId || (!$electionId && $e->current))>{{ $e->name }}</option>@endforeach</select></div>
        @endif
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> {{ t('common.apply') }}</button></div>
    </div></form>
    @include('reports._table')
</div>
@endsection
