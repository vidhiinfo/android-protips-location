@extends('layouts.app')
@section('title', t('field.title'))
@push('head')
<script src="{{ asset('js/field-offline.js') }}" defer></script>
@endpush
@section('content')
@php($i18n = collect(['online', 'offline', 'waiting', 'synced', 'never', 'downloading', 'downloaded', 'failed', 'loginAgain', 'pickBooths', 'tooMany', 'confirmRemove',
    'confirmReplace', 'otherUser', 'expired', 'noResults', 'typeToSearch', 'voted', 'notVoted', 'support', 'neutral', 'opposition', 'notMet', 'family', 'booth',
    'serial', 'house', 'age', 'markOk', 'alreadyMarked', 'notFound', 'confirmUnmark', 'unmarked', 'supportersLeft', 'turnout', 'noElection', 'noPoll', 'rejected',
    'showAll', 'onlySupporters', 'sending', 'sentOk', 'markBtn', 'serialPh', 'votersCount'])->mapWithKeys(fn ($k) => [$k => t('field.' . $k)]))
<div class="container px-3" style="max-width:900px" id="fieldApp"
     data-user="{{ $me->username() }}" data-pack="{{ url('/field/pack') }}" data-sync="{{ url('/field/sync') }}" data-token-url="{{ url('/csrf-token') }}"
     data-max="{{ $maxBooths }}" data-i18n='@json($i18n)'>
    <h4 class="mb-1"><i class="bi bi-cloud-slash"></i> {{ t('field.title') }}</h4>
    <p class="small text-muted mb-2">{{ t('field.intro', $keepDays) }}</p>
    <div id="fStatus" class="alert alert-light border py-2 small d-flex flex-wrap gap-3 align-items-center mb-3">
        <span id="fNet"></span><span id="fWait"></span><span id="fLast" class="text-muted"></span>
        <button type="button" class="btn btn-sm btn-success ms-auto" data-f-sync><i class="bi bi-arrow-repeat"></i> {{ t('field.syncNow') }}</button>
    </div>

    <form id="fDownload" class="card card-body py-2 mb-3" data-online-only>
        <div class="fw-semibold small mb-1"><i class="bi bi-cloud-download"></i> {{ t('field.downloadTitle', $maxBooths) }}</div>
        <div class="row g-2 align-items-end">
            @unless($me->scoped())
            <div class="col-12 col-sm-5"><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label>
                <select name="assemblyNo" class="form-select form-select-sm" data-f-assembly>@foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @else<input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}">@endunless
            <div class="col-12"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label>
                <div class="d-flex flex-wrap gap-1" style="max-height:7.5rem;overflow:auto">
                    @foreach($booths as $b)<label class="btn btn-sm btn-outline-secondary"><input type="checkbox" name="booths[]" value="{{ $b }}" class="form-check-input me-1">{{ $b }}</label>@endforeach
                </div></div>
            <div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-cloud-download"></i> {{ t('field.download') }}</button></div>
        </div>
    </form>

    <div id="fHave" class="small mb-2"></div>

    <ul class="nav nav-tabs mb-2 d-none" id="fTabs">
        <li class="nav-item"><a class="nav-link active" href="#" data-f-tab="search"><i class="bi bi-search"></i> {{ t('field.tabSearch') }}</a></li>
        @if($canPoll)<li class="nav-item"><a class="nav-link" href="#" data-f-tab="poll"><i class="bi bi-check2-square"></i> {{ t('field.tabPoll') }}</a></li>@endif
    </ul>
    <div id="fSearch" class="d-none">
        <input type="search" id="fQ" class="form-control mb-2" placeholder="{{ t('field.searchPh') }}" autocomplete="off" data-scan="input" data-voice="names">
        <div id="fResults"></div>
    </div>
    <div id="fPoll" class="d-none"></div>
</div>
@endsection
