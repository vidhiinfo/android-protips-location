@extends('layouts.app')
@section('title', t('result.entry'))
@section('content')
<div class="container-fluid px-3" style="max-width:1100px">
    @include('partials.flash')
    <h4 class="mb-3"><i class="bi bi-pencil-square"></i> {{ t('result.entry') }}</h4>
    <form method="get" class="card card-body mb-3"><div class="row g-2">
        <div class="col-md-5"><label class="form-label small">{{ t('master.election') }}</label>@include('results._election-select')</div>
        <div class="col-md-4"><label class="form-label small">{{ t('col.assemblyNo') }}</label>
            <select name="assemblyNo" class="form-select form-select-sm" data-autosubmit><option value="">--</option>
                @foreach($seats as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
    </div></form>
    @if($election && !$seats)<div class="alert alert-info">{{ t('result.noContests') }}</div>@endif
    @if($boothList)
    <div class="mb-3"><div class="small text-muted mb-1">{{ t('result.pickBooth') }}</div>
        <div class="d-flex flex-wrap gap-1">@foreach($boothList as $b)
            <a class="btn btn-sm {{ $b->booth_no == $boothNo ? 'btn-primary' : (in_array($b->booth_no, $reported) ? 'btn-outline-success booth-done' : 'btn-outline-secondary') }}"
               href="{{ url('/results/entry') }}?electionId={{ $election->id }}&assemblyNo={{ $assemblyNo }}&boothNo={{ $b->booth_no }}" title="{{ $b->name }}">{{ $b->booth_no }}</a>
        @endforeach</div>
        <div class="small text-muted mt-1"><span class="badge booth-done text-success border border-success">&nbsp;</span> {{ t('result.reportedLegend') }}</div></div>
    @endif
    @if($entries !== null)
    <form method="post" action="{{ url('/results/entry') }}" class="card">@csrf
        <input type="hidden" name="electionId" value="{{ $election->id }}"><input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}"><input type="hidden" name="boothNo" value="{{ $boothNo }}">
        <div class="card-header fw-semibold">{{ t('col.boothNo') }} {{ $boothNo }}</div>
        @if(!$entries)<div class="card-body text-muted">{{ t('result.noCandidates') }}</div>
        @else
        <table class="table mb-0 align-middle"><thead class="table-light"><tr><th>{{ t('master.ballotNo') }}</th><th>{{ t('result.candidate') }}</th><th>{{ t('result.party') }}</th><th style="width:180px">{{ t('result.votes') }}</th></tr></thead>
            <tbody>@foreach($entries as $en)
                <tr><td>{{ $en['candidate']->ballot_no }}</td><td>{{ $en['candidate']->name }}@if($en['candidate']->name_local)<span class="local-text" dir="auto">{{ $en['candidate']->name_local }}</span>@endif</td>
                    <td>{{ $en['candidate']->partyShort() }}</td>
                    <td><input type="text" inputmode="numeric" class="form-control text-end" name="votes[{{ $en['candidate']->id }}]" value="{{ $en['votes'] }}" required></td></tr>
            @endforeach</tbody></table>
        <div class="card-footer d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ t('result.saveNext') }}</button>
            <a class="btn btn-outline-secondary" href="{{ url('/results/contest') }}?electionId={{ $election->id }}&assemblyNo={{ $assemblyNo }}">{{ t('result.viewResult') }}</a></div>
        @endif
    </form>
    @endif
</div>
@endsection
