@extends('layouts.app')
@section('title', t('off.title'))
@push('head')
<script src="{{ asset('js/offline-survey.js') }}" defer></script>
@endpush
@section('content')
@php($i18n = collect(['downloaded', 'houses', 'waiting', 'sent', 'sending', 'offlineNow', 'onlineNow', 'loginAgain', 'failed', 'conflicts', 'errors', 'confirmRemove',
    'confirmReplace', 'expired', 'nothing', 'saved', 'locSaved', 'locFailed', 'locTaking', 'otherUser', 'downloading', 'tooOld'])
    ->mapWithKeys(fn ($k) => [$k => t('off.' . $k)])->merge([
        'members' => t('map.members'), 'done' => t('fs.done'), 'pending' => t('fs.pending'), 'save' => t('common.save'), 'back' => t('fs.allHouses'),
        'mobile' => t('col.mobile'), 'altMobile' => t('col.altMobile'), 'political' => t('col.politicalStatus'), 'social' => t('col.socialStatus'),
        'caste' => t('col.caste'), 'subCaste' => t('col.subCaste'), 'scheme' => t('col.beneficiary1'), 'remarks' => t('col.remarks'),
        'consent' => t('privacy.consentShort'), 'consentY' => t('privacy.consent.Y'), 'consentN' => t('privacy.consent.N'), 'consentU' => t('privacy.consent.U'),
        'location' => t('off.location'), 'age' => t('col.age'), 'house' => t('fs.house'), 'find' => t('fs.findHouse'),
        'S' => t('an.support'), 'N' => t('an.neutral'), 'O' => t('an.opposition'), 'U' => t('fs.notMet'),
    ]))
<div class="container px-3 survey" style="max-width:820px" id="offlineApp"
     data-user="{{ $me->username() }}" data-sync="{{ url('/survey/offline/sync') }}" data-token-url="{{ url('/csrf-token') }}"
     data-i18n='@json($i18n)'>
    <h4 class="mb-1"><i class="bi bi-cloud-slash"></i> {{ t('off.title') }}</h4>
    <p class="small text-muted">{{ t('off.intro', $keepDays) }}</p>
    <div id="offNet" class="small mb-2"></div>

    <div data-online-only>
        @include('survey._pick')
        @if($boothNo)
        <button type="button" class="btn btn-primary w-100 mb-3" data-off-download="{{ url('/survey/offline/data') . '?' . http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}">
            <i class="bi bi-cloud-download"></i> {{ t('off.download', $boothNo) }}</button>
        @endif
    </div>

    <div id="offStatus" class="card card-body py-2 mb-3 d-none">
        <div id="offStatusText" class="small"></div>
        <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-success btn-sm flex-fill" data-off-sync><i class="bi bi-cloud-upload"></i> {{ t('off.sendNow') }}</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-off-remove><i class="bi bi-trash"></i> {{ t('off.remove') }}</button>
        </div>
        <div id="offResult" class="small mt-2"></div>
    </div>

    <div id="offList" class="d-none">
        <input type="search" id="offFind" class="form-control mb-2" placeholder="{{ t('fs.findHouse') }}">
        <div class="list-group" id="offHouses"></div>
    </div>
    <div id="offHouse" class="d-none"></div>
</div>
@endsection
