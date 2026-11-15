@extends('layouts.app')
@section('title', t('nav.reports'))
@section('content')
<div class="container" style="max-width:1100px">
    <h4 class="mb-3"><i class="bi bi-bar-chart"></i> {{ t('nav.reports') }}</h4>
    @foreach($sections as $sec => $ids)
    <h6 class="text-uppercase text-muted small mt-3 mb-2">{{ t('report.section.'.$sec) }}</h6>
    <div class="row g-3">@foreach($ids as $r) @if(!in_array($r, $adminOnly) || can('REPORT_SENSITIVE'))
        <div class="col-md-6 col-lg-4"><a class="card h-100 text-decoration-none report-card" href="{{ url('/reports/'.$r) }}"><div class="card-body">
            <h6 class="card-title">{{ t('report.'.$r) }} @if(in_array($r, $adminOnly))<i class="bi bi-shield-lock text-warning" title="{{ t('report.adminOnly') }}"></i>@endif</h6>
            <p class="card-text small text-muted mb-0">{{ t('report.'.$r.'.desc') }}</p></div></a></div>
    @endif @endforeach</div>
    @endforeach
</div>
@endsection
