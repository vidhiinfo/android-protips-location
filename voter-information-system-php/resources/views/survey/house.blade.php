@extends('layouts.app')
@section('title', t('fs.house') . ' ' . $v->house_no)
@section('content')
@php($back = url('/survey') . '?' . http_build_query(['assemblyNo' => $v->assembly_no, 'boothNo' => $v->booth_no]))
<div class="container px-3 survey" style="max-width:820px">
    @include('partials.flash')
    <div class="d-flex align-items-center gap-2 mb-2">
        <a href="{{ $back }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul"></i> {{ t('fs.allHouses') }}</a>
        <div class="me-auto"></div>
        @if($prev)<a href="{{ url('/survey/house/'.$prev) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i></a>@endif
        @if($pos)<span class="small text-muted">{{ $pos }} / {{ $count }}</span>@endif
        @if($next)<a href="{{ url('/survey/house/'.$next) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-right"></i></a>@endif
    </div>
    <h4 class="mb-0"><i class="bi bi-house-door"></i> {{ t('fs.house') }} {{ $v->house_no ?: '-' }}</h4>
    <p class="small text-muted mb-2">{{ t('col.boothNo') }} {{ $v->booth_no }}@if($v->ward_no) · {{ t('col.wardNo') }} {{ $v->ward_no }}@endif · {{ $v->fullAddress() }} · {{ t('household.count', $members->count()) }}</p>

    <form method="post" action="{{ url('/survey/house/'.$v->id) }}" id="surveyForm">@csrf
    <input type="hidden" name="next" value="{{ $next }}">
    <datalist id="dl-status">@foreach($statuses as $s)<option value="{{ $s }}">@endforeach</datalist>
    <datalist id="dl-scheme">@foreach($schemes as $s)<option value="{{ $s }}">@endforeach</datalist>
    @if($members->count() > 1)
    <div class="card card-body py-2 mb-2 bg-light"><div class="small fw-semibold mb-1">{{ t('fs.sameForAll') }}</div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-copy="caste,sub_caste"><i class="bi bi-files"></i> {{ t('col.caste') }}</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-copy="political_status"><i class="bi bi-files"></i> {{ t('col.politicalStatus') }}</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-copy="place_of_origin"><i class="bi bi-files"></i> {{ t('col.placeOfOrigin') }}</button>
        </div><div class="small text-muted mt-1">{{ t('fs.copyHint') }}</div></div>
    @endif
    @foreach($members as $i => $m)
    @php($val = fn ($f) => old("m.{$m->id}.$f", $m->$f))
    <div class="card mb-2 member {{ $m->deleted ? 'opacity-50' : '' }}" data-member>
        <div class="card-body pb-2">
            <div class="d-flex align-items-baseline gap-2 mb-2">
                <span class="badge text-bg-secondary">{{ $m->serial_no }}</span>
                <span class="fw-semibold">{{ $m->name }}</span>
                <span class="small text-muted">{{ $m->genderLabel() }}, {{ $m->age }}</span>
                @if($m->booth_no != $v->booth_no)<span class="badge text-bg-info">{{ t('col.boothNo') }} {{ $m->booth_no }}</span>@endif
                @if($m->deleted)<span class="badge text-bg-danger">{{ t('col.deleted') }}</span>@endif
            </div>
            <div class="small text-muted mb-2">{{ $m->relationDisplay() }} · <code>{{ $m->epic_no }}</code></div>
            <div class="row g-2">
                <div class="col-12 col-sm-6"><label class="form-label small mb-0">{{ t('col.mobile') }}</label>
                    <input name="m[{{ $m->id }}][mobile]" value="{{ $val('mobile') }}" class="form-control" inputmode="tel" maxlength="14" autocomplete="off"></div>
                <div class="col-12 col-sm-6"><label class="form-label small mb-0">{{ t('col.politicalStatus') }}</label>
                    <input name="m[{{ $m->id }}][political_status]" value="{{ $val('political_status') }}" class="form-control" list="dl-status" data-f="political_status" maxlength="100"></div>
                @php($castes = array_keys($casteTree))
                @if($val('caste') && !in_array($val('caste'), $castes))@php($castes[] = $val('caste'))@endif
                <div class="col-6"><label class="form-label small mb-0">{{ t('col.caste') }}</label>
                    @if($casteTree)
                    <select name="m[{{ $m->id }}][caste]" class="form-select" data-f="caste" data-caste-select data-sub-select="sub-{{ $m->id }}" data-map='@json((object) $casteTree)'>
                        <option value="">--</option>@foreach($castes as $c)<option value="{{ $c }}" @selected($c === $val('caste'))>{{ $c }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][caste]" value="{{ $val('caste') }}" class="form-control" data-f="caste">@endif</div>
                <div class="col-6"><label class="form-label small mb-0">{{ t('col.subCaste') }}</label>
                    @if($casteTree)
                    @php($subs = $casteTree[$val('caste')] ?? [])
                    @if($val('sub_caste') && !in_array($val('sub_caste'), $subs))@php($subs[] = $val('sub_caste'))@endif
                    <select name="m[{{ $m->id }}][sub_caste]" id="sub-{{ $m->id }}" class="form-select" data-f="sub_caste">
                        <option value="">--</option>@foreach($subs as $s)<option value="{{ $s }}" @selected($s === $val('sub_caste'))>{{ $s }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][sub_caste]" value="{{ $val('sub_caste') }}" class="form-control" data-f="sub_caste">@endif</div>
            </div>
            <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#more-{{ $m->id }}">{{ t('fs.more') }} <i class="bi bi-chevron-down"></i></a>
            <div class="collapse {{ $val('beneficiary1') || $val('social_status') || $val('place_of_origin') || $val('membership_no') ? 'show' : '' }}" id="more-{{ $m->id }}"><div class="row g-2 mt-0">
                @foreach(['beneficiary1', 'beneficiary2', 'beneficiary3'] as $bf)
                <div class="col-12 col-sm-4"><label class="form-label small mb-0">{{ t('col.'.$bf) }}</label>
                    <input name="m[{{ $m->id }}][{{ $bf }}]" value="{{ $val($bf) }}" class="form-control" list="dl-scheme" maxlength="150"></div>
                @endforeach
                <div class="col-12 col-sm-4"><label class="form-label small mb-0">{{ t('col.socialStatus') }}</label>
                    <input name="m[{{ $m->id }}][social_status]" value="{{ $val('social_status') }}" class="form-control" maxlength="100"></div>
                <div class="col-6 col-sm-4"><label class="form-label small mb-0">{{ t('col.placeOfOrigin') }}</label>
                    <input name="m[{{ $m->id }}][place_of_origin]" value="{{ $val('place_of_origin') }}" class="form-control" data-f="place_of_origin" maxlength="100"></div>
                <div class="col-6 col-sm-4"><label class="form-label small mb-0">{{ t('col.membershipNo') }}</label>
                    <input name="m[{{ $m->id }}][membership_no]" value="{{ $val('membership_no') }}" class="form-control" maxlength="30"></div>
            </div></div>
        </div>
    </div>
    @endforeach
    <div class="survey-actions d-flex gap-2 py-2">
        <button name="go" value="stay" class="btn btn-outline-primary flex-fill"><i class="bi bi-check2"></i> {{ t('common.save') }}</button>
        @if($next)<button name="go" value="next" class="btn btn-primary flex-fill"><i class="bi bi-check2-all"></i> {{ t('fs.saveNext') }}</button>@endif
    </div>
    </form>
</div>
@endsection
