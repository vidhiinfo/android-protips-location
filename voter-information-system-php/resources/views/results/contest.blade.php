@extends('layouts.app')
@section('title', t('result.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h4 class="me-auto mb-0"><i class="bi bi-trophy"></i> {{ $c['assembly_no'] }} - {{ $c['assembly_name'] }}@if($c['ward_no']) <span class="badge text-bg-info fs-6">{{ t('col.wardNo') }} {{ $c['ward_no'] }}</span>@endif <small class="text-muted fs-6">{{ $c['election']->name }}</small></h4>
        <a class="btn btn-sm btn-success no-print" href="{{ url('/results/contest/export/xlsx') }}?electionId={{ $c['election']->id }}&assemblyNo={{ $c['assembly_no'] }}{{ $c['ward_no'] ? '&wardNo='.$c['ward_no'] : '' }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a class="btn btn-sm btn-danger no-print" href="{{ url('/results/contest/export/pdf') }}?electionId={{ $c['election']->id }}&assemblyNo={{ $c['assembly_no'] }}{{ $c['ward_no'] ? '&wardNo='.$c['ward_no'] : '' }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <button class="btn btn-sm btn-secondary no-print" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
    </div>
    @if($wards)
    <form method="get" class="d-flex gap-2 align-items-end mb-3 no-print">
        <input type="hidden" name="electionId" value="{{ $c['election']->id }}"><input type="hidden" name="assemblyNo" value="{{ $c['assembly_no'] }}">
        <div><label class="form-label small mb-0">{{ t('col.wardNo') }}</label>
            <select name="wardNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($wards as $w)<option value="{{ $w }}" @selected($w == $wardNo)>{{ $w }}</option>@endforeach</select></div>
    </form>
    @endif
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('result.totalVotes') }}</div><div class="num">{{ $c['total_votes'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('result.electors') }}</div><div class="num">{{ $c['electors'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('result.turnoutPct') }}</div><div class="num">{{ number_format($c['turnout'], 2) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('result.boothsReported') }}</div><div class="num">{{ $c['booths_reported'] }}/{{ $c['booths_total'] }}</div></div></div>
    </div>
    @if($c['winner'])<div class="alert alert-success"><i class="bi bi-award"></i> {{ $c['complete'] ? t('result.won') : t('result.leading') }}: <b>{{ $c['winner']['candidate']->name }}</b>
        @if($c['winner']['candidate']->name_local)<span dir="auto">({{ $c['winner']['candidate']->name_local }})</span>@endif - <b>{{ $c['winner']['candidate']->partyShort() }}</b>, {{ t('result.margin') }} <b>{{ $c['margin'] }}</b></div>@endif
    <div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>{{ t('result.rank') }}</th><th>{{ t('result.candidate') }}</th><th>{{ t('result.party') }}</th><th class="text-end">{{ t('result.votes') }}</th><th style="width:30%">{{ t('result.voteSharePct') }}</th></tr></thead>
        <tbody>@foreach($c['totals'] as $tt)
            <tr><td>{{ $tt['rank'] }}</td><td>{{ $tt['candidate']->name }}@if($tt['candidate']->name_local)<span class="local-text" dir="auto">{{ $tt['candidate']->name_local }}</span>@endif</td>
                <td>{{ $tt['candidate']->partyShort() }}</td><td class="text-end fw-semibold">{{ $tt['votes'] }}</td>
                <td><div class="d-flex align-items-center gap-2"><div class="vote-bar flex-fill"><span style="width:{{ $tt['share'] }}%;background:{{ $tt['candidate']->partyColor() }}"></span></div>
                    <span class="small">{{ number_format($tt['share'], 2) }}</span></div></td></tr>
        @endforeach</tbody></table></div></div>
    <h5>{{ t('result.boothWise') }}</h5>
    <div class="table-responsive bg-white border rounded mb-4"><table class="table table-sm table-striped mb-0">
        <thead class="table-light"><tr><th>{{ t('col.boothNo') }}</th><th>{{ t('col.boothName') }}</th>
            @foreach($c['totals'] as $tt)<th class="text-end" title="{{ $tt['candidate']->name }}">{{ $tt['candidate']->partyShort() }}</th>@endforeach
            <th class="text-end">{{ t('report.total') }}</th><th>{{ t('result.leader') }}</th></tr></thead>
        <tbody>
        @forelse($c['booths'] as $b)
            <tr><td>{{ $b['booth_no'] }}</td><td class="small">{{ $b['booth_name'] }}</td>
                @foreach($c['totals'] as $tt)<td class="text-end">{{ $b['votes'][$tt['candidate']->id] ?? '' }}</td>@endforeach
                <td class="text-end fw-semibold">{{ $b['total'] }}</td><td>{{ $b['leader']?->partyShort() }}</td></tr>
        @empty
            <tr><td colspan="20" class="text-center text-muted py-3">{{ t('result.noBooths') }}</td></tr>
        @endforelse
        </tbody></table></div>
</div>
@endsection
