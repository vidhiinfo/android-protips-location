@extends('layouts.app')
@section('title', t('map.title'))
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
@endpush
@section('content')
<div class="container-fluid px-3" style="max-width:1100px">
    @include('partials.flash')
    <div class="d-flex align-items-center gap-2 mb-1">
        <h4 class="mb-0 me-auto"><i class="bi bi-geo-alt"></i> {{ t('map.title') }}</h4>
        <a href="{{ url('/survey') . '?' . http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul"></i> {{ t('fs.allHouses') }}</a>
    </div>
    <p class="small text-muted">{{ t('map.intro') }}</p>
    @include('survey._pick')
    @if($boothNo)
    <div class="d-flex flex-wrap gap-3 small mb-2">
        <span><span class="map-dot" style="background:#198754"></span> {{ t('fs.done') }}</span>
        <span><span class="map-dot" style="background:#fd7e14"></span> {{ t('map.partly') }}</span>
        <span><span class="map-dot" style="background:#dc3545"></span> {{ t('fs.pending') }}</span>
        <span class="ms-auto text-muted">{{ t('map.counts', count($points), $missing) }}</span>
    </div>
    @if($points)
    <div id="houseMap" class="border rounded" style="height:65vh;min-height:360px"
         data-points='@json($points)' data-tiles="{{ $tiles }}" data-attribution="{{ $attribution }}"
         data-l-members="{{ t('map.members') }}" data-l-open="{{ t('map.open') }}"></div>
    @else
    <div class="alert alert-info">{{ t('map.none') }}</div>
    @endif
    @else
    <div class="alert alert-info">{{ t('fs.pickBoothFirst') }}</div>
    @endif
</div>
@endsection
