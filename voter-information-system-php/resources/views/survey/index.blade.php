@extends('layouts.app')
@section('title', t('fs.title'))
@section('content')
<div class="container px-3 survey" style="max-width:820px">
    @include('partials.flash')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <h4 class="mb-0 me-auto"><i class="bi bi-clipboard2-check"></i> {{ t('fs.title') }}</h4>
        <a href="{{ url('/survey/map') . '?' . http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-map"></i> {{ t('map.title') }}</a>
        <a href="{{ url('/survey/offline') . '?' . http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-cloud-slash"></i> {{ t('off.title') }}</a>
    </div>
    <p class="small text-muted">{{ t('fs.intro') }}</p>
    <form method="get" class="card card-body py-2 mb-3"><div class="row g-2 align-items-end">
        @unless($me->scoped())
        <div class="col-12 col-sm-5"><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label>
            <select name="assemblyNo" class="form-select" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
        @endunless
        @if($wards)
        <div class="col-6 col-sm-3"><label class="form-label small mb-0">{{ t('col.wardNo') }}</label>
            <select name="wardNo" class="form-select" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($wards as $w)<option value="{{ $w }}" @selected($w == $wardNo)>{{ $w }}</option>@endforeach</select></div>
        @endif
        <div class="col-6 col-sm-4"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label>
            <select name="boothNo" class="form-select" data-autosubmit><option value="">{{ t('fs.pickBooth') }}</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
        @if($boothNo)
        <div class="col-7 col-sm-5"><label class="form-label small mb-0">{{ t('col.houseNo') }}</label>
            <input name="house" value="{{ $house }}" class="form-control" placeholder="{{ t('fs.findHouse') }}"></div>
        <div class="col-5 col-sm-4 form-check ms-2 mb-2"><input class="form-check-input" type="checkbox" name="pending" value="1" id="pending" @checked($pending) data-autosubmit>
            <label class="form-check-label small" for="pending">{{ t('fs.onlyPending') }}</label></div>
        <div class="col-12 col-sm-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
        @endif
    </div></form>

    @if($stats)
    <div class="row g-2 mb-3">
        <div class="col-6"><div class="stat"><div class="small text-muted">{{ t('fs.housesDone') }}</div>
            <div class="num">{{ $stats['housesDone'] }} / {{ $stats['houses'] }}</div>
            <div class="progress mt-1" style="height:6px"><div class="progress-bar bg-success" style="width:{{ $stats['houses'] ? round(100 * $stats['housesDone'] / $stats['houses']) : 0 }}%"></div></div></div></div>
        <div class="col-6"><div class="stat"><div class="small text-muted">{{ t('fs.votersDone') }}</div>
            <div class="num">{{ $stats['votersDone'] }} / {{ $stats['voters'] }}</div>
            <div class="progress mt-1" style="height:6px"><div class="progress-bar" style="width:{{ $stats['voters'] ? round(100 * $stats['votersDone'] / $stats['voters']) : 0 }}%"></div></div></div></div>
    </div>
    <div class="list-group">
        @forelse($houses as $h)
        <a href="{{ url('/survey/house/'.$h['anchor']) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
            <span class="fs-5 fw-semibold" style="min-width:5.5rem">{{ $h['house'] ?: '-' }}</span>
            <span class="small text-muted me-auto text-truncate">{{ $h['addr'] }}</span>
            <span class="badge text-bg-light border">{{ t('household.count', $h['n']) }}</span>
            @if($h['done'] >= $h['n'])<span class="badge text-bg-success"><i class="bi bi-check-lg"></i> {{ t('fs.done') }}</span>
            @elseif($h['done'])<span class="badge text-bg-warning">{{ $h['done'] }}/{{ $h['n'] }}</span>
            @else<span class="badge text-bg-secondary">{{ t('fs.pending') }}</span>@endif
            <i class="bi bi-chevron-right text-muted"></i>
        </a>
        @empty
        <div class="list-group-item text-muted text-center py-4">{{ t('list.empty') }}</div>
        @endforelse
    </div>
    @else
    <div class="alert alert-info">{{ t('fs.pickBoothFirst') }}</div>
    @endif
</div>
@endsection
