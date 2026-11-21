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
    <p class="small text-muted mb-2">{{ t('col.boothNo') }} {{ $v->booth_no }}@if($v->ward_no && \App\Models\Voter::showWard()) · {{ t('col.wardNo') }} {{ $v->ward_no }}@endif · {{ $v->fullAddress() }} · {{ t('household.count', $members->count()) }}</p>

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
            <button type="button" class="btn btn-sm btn-outline-primary" data-copy="social_status"><i class="bi bi-files"></i> {{ t('col.socialStatus') }}</button>
        </div><div class="small text-muted mt-1">{{ t('fs.copyHint2') }}</div></div>
    @endif
    @foreach($members as $i => $m)
    @php($val = fn ($f) => old("m.{$m->id}.$f", $m->$f))
    @php($done = $m->political_status && $m->caste)
    @php($sel = function ($f, $opts) use ($m, $val) { $cur = (string) $val($f); if ($cur !== '' && !isset($opts[$cur])) $opts[$cur] = $cur; return $opts; })
    <div class="card mb-2 member {{ $m->deleted ? 'opacity-50' : '' }}" data-member>
        <div class="card-body pb-2">
            <div class="d-flex align-items-baseline gap-2 mb-1 flex-wrap">
                <span class="badge text-bg-secondary">{{ $m->serial_no }}</span>
                <span class="fw-semibold">{{ $m->name }}</span>
                <span class="small text-muted">{{ $m->genderLabel() }}, {{ $m->age }}</span>
                @if($m->booth_no != $v->booth_no)<span class="badge text-bg-info">{{ t('col.boothNo') }} {{ $m->booth_no }}</span>@endif
                @if($m->deleted)<span class="badge text-bg-danger">{{ t('col.deleted') }}</span>@endif
                @if($done)<a class="ms-auto small" data-bs-toggle="collapse" href="#f-{{ $m->id }}"><i class="bi bi-check-circle-fill text-success"></i> {{ t('fs.doneEdit') }}</a>@endif
            </div>
            <div class="small text-muted mb-2">{{ $m->relationDisplay() }} · <code>{{ $m->epic_no }}</code>
                @if($done) · <span class="text-body">{{ $m->political_status }} · {{ $m->caste }}{{ $m->sub_caste ? ' / ' . $m->sub_caste : '' }}{{ $m->mobile ? ' · ' . $m->mobile : '' }}</span>@endif</div>
            <div class="collapse {{ $done ? '' : 'show' }}" id="f-{{ $m->id }}">
            {{-- one tap for the political status; the full list stays in the drop-down --}}
            @if($quick)
            <div class="d-flex flex-wrap gap-1 mb-2" data-quick="m[{{ $m->id }}][political_status]">
                @foreach($quick as $lean => $name)
                <button type="button" class="btn btn-sm {{ $val('political_status') === $name ? 'btn-' . ['S' => 'primary', 'N' => 'secondary', 'O' => 'danger', 'U' => 'dark'][$lean] : 'btn-outline-' . ['S' => 'primary', 'N' => 'secondary', 'O' => 'danger', 'U' => 'dark'][$lean] }}"
                        data-quick-value="{{ $name }}" data-quick-class="{{ ['S' => 'primary', 'N' => 'secondary', 'O' => 'danger', 'U' => 'dark'][$lean] }}">{{ t(['S' => 'an.support', 'N' => 'an.neutral', 'O' => 'an.opposition', 'U' => 'fs.notMet'][$lean]) }}</button>
                @endforeach
            </div>
            @endif
            <div class="row g-2">
                @php($sel2 = fn ($f, $label, $col = 'col-6 col-md-3') => compact('f', 'label', 'col'))
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.mobile') }}</label>
                    <input name="m[{{ $m->id }}][mobile]" value="{{ $val('mobile') }}" class="form-control" type="tel" inputmode="tel" maxlength="14" autocomplete="off"></div>
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.altMobile') }}</label>
                    <input name="m[{{ $m->id }}][alt_mobile]" value="{{ $val('alt_mobile') }}" class="form-control" type="tel" inputmode="tel" maxlength="14" autocomplete="off"></div>
                @if(\App\Services\Privacy\PrivacyService::enabled())
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('privacy.consentShort') }}</label>
                    <select name="m[{{ $m->id }}][consent]" class="form-select">@foreach(['', 'Y', 'N'] as $c)<option value="{{ $c }}" @selected(($m->consent_contact ?? '') === $c)>{{ t('privacy.consent.' . ($c ?: 'U')) }}</option>@endforeach</select></div>
                @endif
                @foreach(['political_status' => 'col.politicalStatus', 'social_status' => 'col.socialStatus'] as $sf => $sl)
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t($sl) }}</label>
                    @if($statusOpts[$sf])
                    <select name="m[{{ $m->id }}][{{ $sf }}]" class="form-select" data-f="{{ $sf }}"><option value="">--</option>
                        @foreach($sel($sf, $statusOpts[$sf]) as $o => $lbl)<option value="{{ $o }}" @selected((string) $o === (string) $val($sf))>{{ $lbl }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][{{ $sf }}]" value="{{ $val($sf) }}" class="form-control" data-f="{{ $sf }}" maxlength="100">@endif</div>
                @endforeach
                @php($castes = array_keys($casteTree))
                @if($val('caste') && !in_array($val('caste'), $castes))@php($castes[] = $val('caste'))@endif
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.caste') }}</label>
                    @if($casteTree)
                    <select name="m[{{ $m->id }}][caste]" class="form-select" data-f="caste" data-caste-select data-sub-select="sub-{{ $m->id }}" data-map='@json((object) $casteTree)' data-labels='@json((object) \App\Models\Caste::labels())'>
                        <option value="">--</option>@foreach($castes as $c)<option value="{{ $c }}" @selected($c === $val('caste'))>{{ \App\Models\Caste::labels()[$c] ?? $c }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][caste]" value="{{ $val('caste') }}" class="form-control" data-f="caste">@endif</div>
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.subCaste') }}</label>
                    @if($casteTree)
                    @php($subs = $casteTree[$val('caste')] ?? [])
                    @if($val('sub_caste') && !in_array($val('sub_caste'), $subs))@php($subs[] = $val('sub_caste'))@endif
                    <select name="m[{{ $m->id }}][sub_caste]" id="sub-{{ $m->id }}" class="form-select" data-f="sub_caste">
                        <option value="">--</option>@foreach($subs as $s)<option value="{{ $s }}" @selected($s === $val('sub_caste'))>{{ \App\Models\Caste::labels()[$s] ?? $s }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][sub_caste]" value="{{ $val('sub_caste') }}" class="form-control" data-f="sub_caste">@endif</div>
                @foreach(['beneficiary1', 'beneficiary2', 'beneficiary3'] as $bf)
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.'.$bf) }}</label>
                    @if($statusOpts['beneficiary'])
                    <select name="m[{{ $m->id }}][{{ $bf }}]" class="form-select"><option value="">--</option>
                        @foreach($sel($bf, $statusOpts['beneficiary']) as $o => $lbl)<option value="{{ $o }}" @selected((string) $o === (string) $val($bf))>{{ $lbl }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][{{ $bf }}]" value="{{ $val($bf) }}" class="form-control" maxlength="150">@endif</div>
                @endforeach
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.organisationalStatus') }}</label>
                    @if($statusOpts['organisational_status'])
                    <select name="m[{{ $m->id }}][organisational_status]" class="form-select"><option value="">--</option>
                        @foreach($sel('organisational_status', $statusOpts['organisational_status']) as $o => $lbl)<option value="{{ $o }}" @selected((string) $o === (string) $val('organisational_status'))>{{ $lbl }}</option>@endforeach</select>
                    @else<input name="m[{{ $m->id }}][organisational_status]" value="{{ $val('organisational_status') }}" class="form-control" maxlength="100">@endif</div>
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.dob') }}</label>
                    <input name="m[{{ $m->id }}][dob]" value="{{ old("m.{$m->id}.dob", $m->dob?->format('Y-m-d')) }}" class="form-control" type="date" max="{{ date('Y-m-d') }}"></div>
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.placeOfOrigin') }}</label>
                    <input name="m[{{ $m->id }}][place_of_origin]" value="{{ $val('place_of_origin') }}" class="form-control" data-f="place_of_origin" maxlength="150"></div>
                <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('col.membershipNo') }}</label>
                    <input name="m[{{ $m->id }}][membership_no]" value="{{ $val('membership_no') }}" class="form-control" maxlength="50"></div>
                <div class="col-12 col-md-6"><label class="form-label small mb-0">{{ t('col.remarks') }}</label>
                    <input name="m[{{ $m->id }}][remarks]" value="{{ $val('remarks') }}" class="form-control" maxlength="500" dir="auto"></div>
            </div>
            </div>
        </div>
    </div>
    @endforeach
    <div class="card card-body py-2 mb-2">
        <input type="hidden" name="lat"><input type="hidden" name="lng"><input type="hidden" name="acc">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-geo="geoOut" data-l-taking="{{ t('off.locTaking') }}" data-l-failed="{{ t('off.locFailed') }}" data-l-ready="{{ t('map.ready') }}">
                <i class="bi bi-geo-alt"></i> {{ $loc ? t('map.retake') : t('off.location') }}</button>
            <span class="small text-muted" id="geoOut">@if($loc){{ t('map.saved', $loc['acc'] ?? '-', \Illuminate\Support\Str::limit($loc['at'], 16, '')) }}@endif</span>
            <a class="small ms-auto" href="{{ url('/survey/map') . '?' . http_build_query(['assemblyNo' => $v->assembly_no, 'boothNo' => $v->booth_no]) }}"><i class="bi bi-map"></i> {{ t('map.title') }}</a>
        </div>
    </div>
    <div class="survey-actions d-flex gap-2 py-2">
        <button name="go" value="stay" class="btn btn-outline-primary flex-fill"><i class="bi bi-check2"></i> {{ t('common.save') }}</button>
        @if($next)<button name="go" value="next" class="btn btn-primary flex-fill"><i class="bi bi-check2-all"></i> {{ t('fs.saveNext') }}</button>@endif
    </div>
    </form>
</div>
@endsection
