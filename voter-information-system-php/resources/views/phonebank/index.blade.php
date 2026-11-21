@extends('layouts.app')
@section('title', t('pb.title'))
@section('content')
@php($keep = ['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo, 'mode' => $mode])
<div class="container px-3" style="max-width:980px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-telephone"></i> {{ t('pb.title') }}</h4><div class="small text-muted">{{ t('pb.intro') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @endunless
            <div><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
            <div><label class="form-label small mb-0">{{ t('pb.list') }}</label><select name="mode" class="form-select form-select-sm" data-autosubmit>
                @foreach(['new', 'callback'] as $m)<option value="{{ $m }}" @selected($m === $mode)>{{ t('pb.mode.' . $m) }}</option>@endforeach</select></div>
        </form>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-4 col-md-2"><div class="stat"><div class="small text-muted">{{ t('pb.left') }}</div><div class="num">{{ $left }}</div></div></div>
        <div class="col-4 col-md-2"><div class="stat"><div class="small text-muted">{{ t('pb.mine') }}</div><div class="num">{{ $mine }}</div></div></div>
        <div class="col-4 col-md-2"><div class="stat"><div class="small text-muted">{{ t('pb.todayAll') }}</div><div class="num">{{ array_sum($stats) }}</div></div></div>
        <div class="col-md-6"><div class="stat small">@forelse($stats as $o => $n)<span class="badge text-bg-light border me-1">{{ t('pb.o.' . $o) }}: {{ $n }}</span>@empty <span class="text-muted">{{ t('pb.noCalls') }}</span>@endforelse</div></div>
    </div>

    @if($voter)
    <form method="post" action="{{ url('/phonebank/' . $voter->id) }}" class="card mb-3">@csrf
        <input type="hidden" name="boothNo" value="{{ $boothNo }}"><input type="hidden" name="mode" value="{{ $mode }}">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <div class="me-auto">
                    <div class="fs-5 fw-semibold">{{ $voter->name }} @if($voter->name_local)<span class="text-muted fs-6">{{ $voter->name_local }}</span>@endif</div>
                    <div class="small text-muted">{{ $voter->relative_name }} · {{ $voter->genderLabel() }} · {{ t('col.age') }} {{ $voter->age ?? '-' }} · {{ t('col.boothNo') }} {{ $voter->booth_no }} / {{ $voter->serial_no }} · {{ $voter->house_no }}</div>
                    <div class="small">@if($voter->political_status)<span class="badge text-bg-light border">{{ $voter->political_status }}</span>@endif @if($voter->caste)<span class="badge text-bg-light border">{{ $voter->caste }}</span>@endif</div>
                </div>
                @if($tel)<a href="tel:{{ $tel }}" class="btn btn-success btn-lg"><i class="bi bi-telephone-outbound"></i> {{ $voter->mobile }}</a>@endif
            </div>
            <hr>
            <div class="small fw-semibold mb-1">{{ t('pb.outcome') }}</div>
            <div class="d-flex flex-wrap gap-2 mb-3" role="group">
                @foreach(\App\Http\Controllers\PhoneBankController::OUTCOMES as $o)
                <input type="radio" class="btn-check" name="outcome" id="o-{{ $o }}" value="{{ $o }}" required @checked($o === 'ANSWERED')>
                <label class="btn btn-sm btn-outline-{{ $o === 'ANSWERED' ? 'success' : (in_array($o, ['WRONG_NUMBER', 'REFUSED']) ? 'danger' : 'secondary') }}" for="o-{{ $o }}">{{ t('pb.o.' . $o) }}</label>
                @endforeach
            </div>
            <div class="row g-2">
                <div class="col-md-6"><div class="small fw-semibold mb-1">{{ t('pb.leaning') }}</div>
                    <div class="d-flex gap-2">@foreach(['S' => 'an.support', 'N' => 'an.neutral', 'O' => 'an.opposition'] as $k => $l)
                        <input type="radio" class="btn-check" name="leaning" id="l-{{ $k }}" value="{{ $k }}"><label class="btn btn-sm btn-outline-primary" for="l-{{ $k }}">{{ t($l) }}</label>@endforeach</div>
                    @if($quick)<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="set_status" value="1" id="setStatus" checked>
                        <label class="form-check-label small" for="setStatus">{{ t('pb.setStatus') }}</label></div>@endif
                </div>
                <div class="col-md-6"><label class="form-label small fw-semibold mb-1">{{ t('pb.callbackAt') }}</label>
                    <input type="datetime-local" name="callback_at" class="form-control form-control-sm">
                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_number" value="1" id="rmNum">
                        <label class="form-check-label small" for="rmNum">{{ t('pb.removeNumber') }}</label></div></div>
                <div class="col-12"><input name="notes" class="form-control" maxlength="500" dir="auto" placeholder="{{ t('pb.notes') }}"></div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary flex-fill"><i class="bi bi-check2-all"></i> {{ t('pb.saveNext') }}</button>
            <button class="btn btn-outline-secondary" formaction="{{ url('/phonebank/' . $voter->id . '/skip') }}" formnovalidate name="assemblyNo" value="{{ $assemblyNo }}">{{ t('pb.skip') }}</button>
        </div>
    </form>
    @if($history->count())
    <h6>{{ t('pb.history') }}</h6>
    @include('phonebank._history', ['calls' => $history])
    @endif
    @else
    <div class="alert alert-success">{{ t('pb.allDone') }}</div>
    @endif
    @if($callers->count())
    <h6 class="mt-3">{{ t('pb.callers') }}</h6>
    <div class="d-flex flex-wrap gap-2">@foreach($callers as $u => $n)<span class="badge text-bg-light border fs-6">{{ $u }}: {{ $n }}</span>@endforeach</div>
    @endif
</div>
@endsection
