@extends('layouts.app')
@section('title', t('worker.title'))
@section('content')
<div class="container" style="max-width:760px">
    @include('partials.flash')
    <h4 class="mb-3"><i class="bi bi-person-badge"></i> {{ $w->exists ? $w->name : t('worker.add') }}</h4>
    <form method="post" action="{{ url($w->exists ? '/workers/'.$w->id : '/workers') }}" class="card card-body">@csrf
        @php($from = parse_url(url()->previous(), PHP_URL_PATH) ?: '')
        @php($from = preg_replace('#^' . preg_quote(rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '', '/'), '#') . '#', '', $from))
        @if(!$w->exists && preg_match('#^/organisation(/\d+)?$#', old('return_to', $from)))<input type="hidden" name="return_to" value="{{ old('return_to', $from) }}">@endif
        <div class="row g-3">
            @php($manual = $w->exists ? !$linked : (old('name') && !old('epic_no')))
            <div class="col-12"><div class="border rounded p-2 bg-light" data-voter-pick data-url="{{ url('/voters/pick') }}"
                data-searching="{{ t('worker.searching') }}" data-none="{{ t('worker.noMatch') }}" data-taken="{{ t('worker.alreadyWorker') }}"
                data-booth-label="{{ t('col.boothNo') }}" data-sr-label="{{ t('list.col.sr') }}">
                <label class="form-label fw-semibold mb-1" for="voter-q"><i class="bi bi-person-vcard"></i> {{ t('worker.fromVoterList') }}</label>
                <input type="hidden" id="epic_no" name="epic_no" data-pick-value value="{{ old('epic_no', $linked?->epic_no) }}">
                <div data-picked class="@if(!$linked) d-none @endif alert alert-success py-2 mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill"></i>
                    <div class="me-auto"><span class="small">{{ t('worker.linkedTo') }}:</span> <b data-picked-name>{{ $linked?->name }}</b> <span class="small" data-picked-info>@if($linked){{ $linked->epic_no }} · {{ $linked->relationDisplay() }} · {{ t('col.boothNo') }} {{ $linked->booth_no }}, {{ t('list.col.sr') }} {{ $linked->serial_no }}@endif</span>
                        @if($linked)<a href="{{ url('/voters/' . $linked->id) }}" class="small ms-1">{{ t('worker.ownVoter') }}</a>@endif</div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-unpick>{{ t('worker.change') }}</button>
                </div>
                <div data-search class="@if($linked) d-none @endif">
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="voter-q" class="form-control" autocomplete="off" placeholder="{{ t('worker.searchPh') }}" maxlength="60" data-scan="input" data-voice="names"></div>
                    <div class="list-group mt-1" data-results></div>
                    <div class="form-text">{{ t('worker.fromVoterListHint') }}@if($w->booth_no) {{ t('worker.thisBoothFirst', $w->booth_no) }}@endif</div>
                </div>
            </div></div>
            <div class="col-md-6" data-name-row>
                <button type="button" class="btn btn-link btn-sm p-0 @if($manual || $linked) d-none @endif" data-bs-toggle="collapse" data-bs-target="#manual-name" data-manual-toggle>{{ t('worker.notInList') }}</button>
                <div id="manual-name" class="collapse @if($manual) show @endif">
                    <label class="form-label">{{ t('worker.name') }} *</label><input name="name" value="{{ old('name', $linked ? '' : $w->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="120">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ t('worker.manualHint') }}</div>
                </div>
            </div>
            <div class="col-md-6"><label class="form-label">{{ t('col.mobile') }}</label><input name="mobile" value="{{ old('mobile', $w->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" inputmode="tel" maxlength="10">@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">{{ t('col.assemblyNo') }} *</label><select name="assembly_no" class="form-select" data-pick-assembly @disabled($me->scoped())>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == old('assembly_no', $w->assembly_no))>{{ $no }} - {{ $name }}</option>@endforeach</select>
                @if($me->scoped())<input type="hidden" name="assembly_no" value="{{ $me->workAssembly() }}">@endif</div>
            <div class="col-md-4"><label class="form-label">{{ t('col.boothNo') }}</label><select name="booth_no" class="form-select" data-pick-booth><option value="">-</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == old('booth_no', $w->booth_no))>{{ $b }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ t('worker.role') }} *</label><select name="role" class="form-select" data-role-select>
                @foreach(\App\Support\WorkerRoles::codes(true, $w->role) as $ro)@continue(\App\Support\WorkerRoles::base($ro) === 'LS_INCHARGE' && $me->scoped())<option value="{{ $ro }}" data-base="{{ \App\Support\WorkerRoles::base($ro) }}" @selected($ro === old('role', $w->role))>{{ role_label($ro) }}</option>@endforeach</select>
                <div class="form-text">{{ t('org.roleHint') }}</div></div>
            @if(count($mandals))
            <div class="col-md-6" data-mandal-field><label class="form-label">{{ t('org.mandal') }}</label><select name="mandal_id" class="form-select"><option value="">-</option>
                @foreach($mandals as $m)<option value="{{ $m->id }}" @selected($m->id == old('mandal_id', $w->mandal_id))>{{ $m->assembly_no }} - {{ $m->name }}</option>@endforeach</select>
                <div class="form-text">{{ t('org.mandalHint') }}</div></div>
            @endif
            <div class="col-md-6"><label class="form-label">{{ t('worker.login') }}</label><select name="username" class="form-select"><option value="">{{ t('worker.noLogin') }}</option>
                @foreach($usernames as $u)<option value="{{ $u }}" @selected($u === old('username', $w->username))>{{ $u }}</option>@endforeach</select>
                <div class="form-text">{{ t('worker.loginHint') }}</div></div>
            <div class="col-md-6 d-flex align-items-center"><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $w->active))><label class="form-check-label" for="active">{{ t('master.active') }}</label></div></div>
            <div class="col-12"><label class="form-label">{{ t('col.remarks') }}</label><input name="notes" value="{{ old('notes', $w->notes) }}" class="form-control" maxlength="300"></div>
        </div>
        <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ t('common.save') }}</button>
            <a href="{{ url($w->exists ? '/workers/'.$w->id : '/workers') }}" class="btn btn-outline-secondary">{{ t('common.cancel') }}</a></div>
    </form>
</div>
@push('scripts')<script src="{{ versioned_asset('js/worker-pick.js') }}"></script>@endpush
@endsection
