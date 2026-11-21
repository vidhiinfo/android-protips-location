@extends('layouts.app')
@section('title', t('otp.account'))
@section('content')
<div class="container" style="max-width:720px">
    @include('partials.flash')
    <h4 class="mb-3"><i class="bi bi-shield-lock"></i> {{ t('otp.account') }}</h4>
    @if($codes)
    <div class="alert alert-warning">
        <b>{{ t('otp.codesTitle') }}</b><div class="small mb-2">{{ t('otp.codesHelp') }}</div>
        <div class="row row-cols-2 row-cols-sm-4 g-2 font-monospace">@foreach($codes as $c)<div class="col"><span class="d-block bg-white border rounded text-center py-1 user-select-all">{{ $c }}</span></div>@endforeach</div>
    </div>
    @endif
    <div class="card mb-3"><div class="card-body">
        @if($u && $u->otp_method)
            <p class="mb-1"><span class="badge text-bg-success">{{ t('otp.on') }}</span> {{ t('otp.method.' . $u->otp_method) }}
                @if($u->otp_method !== 'app' && $u->mobile) · ••••••{{ substr($u->mobile, -4) }}@endif</p>
            <p class="small text-muted">{{ t('otp.since', $u->otp_since?->format('d-m-Y')) }} · {{ t('otp.codesLeft', $left) }}</p>
            <div class="row g-2">
                <div class="col-md-6"><form method="post" action="{{ url('/account/security/recovery') }}">@csrf
                    <label class="form-label small mb-0">{{ t('otp.newCodes') }}</label>
                    <div class="input-group input-group-sm"><input type="password" name="password" class="form-control" placeholder="{{ t('account.current') }}" required>
                        <button class="btn btn-outline-primary">{{ t('otp.generate') }}</button></div></form></div>
                @unless($requiredByPolicy)
                <div class="col-md-6"><form method="post" action="{{ url('/account/security/disable') }}" onsubmit="return confirm(@js(t('otp.offConfirm')))">@csrf
                    <label class="form-label small mb-0">{{ t('otp.turnOff') }}</label>
                    <div class="input-group input-group-sm"><input type="password" name="password" class="form-control" placeholder="{{ t('account.current') }}" required>
                        <button class="btn btn-outline-danger">{{ t('otp.turnOffBtn') }}</button></div></form></div>
                @endunless
            </div>
            <a class="btn btn-sm btn-link px-0 mt-2" href="{{ url('/account/security/setup') }}">{{ t('otp.change') }}</a>
        @else
            <p><span class="badge text-bg-secondary">{{ t('otp.offBadge') }}</span> {{ t('otp.offHelp') }}</p>
            <a class="btn btn-primary" href="{{ url('/account/security/setup') }}"><i class="bi bi-shield-plus"></i> {{ t('otp.turnOn') }}</a>
        @endif
    </div></div>
    <p class="small text-muted">{{ t('otp.about') }}</p>
</div>
@endsection
