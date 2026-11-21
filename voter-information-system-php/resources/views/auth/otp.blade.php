@extends('auth._shell')
@section('card')
<h2 class="h5">{{ t('otp.title') }}</h2>
<p class="text-muted small mb-3">
    @if($u->otp_method === 'app'){{ t('otp.askApp') }}@else{{ t('otp.askSms', $masked) }}@endif
</p>
@if(!empty($error))<div class="alert alert-danger py-2">{{ $error }}</div>@endif
@if(!empty($info))<div class="alert alert-success py-2">{{ $info }}</div>@endif
<form method="post" action="{{ url('/login/otp') }}">@csrf
    <input name="code" class="form-control form-control-lg text-center mb-2" inputmode="numeric" autocomplete="one-time-code" maxlength="12" autofocus required placeholder="123456" style="letter-spacing:.3em">
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="trust" value="1" id="trust"><label class="form-check-label small" for="trust">{{ t('otp.trust', (int) config('voters.security.otp_trust_days', 30)) }}</label></div>
    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-check2-circle"></i> {{ t('otp.verify') }}</button>
</form>
@if($u->otp_method !== 'app')
<form method="post" action="{{ url('/login/otp/resend') }}" class="mt-2">@csrf<button class="btn btn-link btn-sm px-0">{{ t('otp.resend') }}</button></form>
@endif
<p class="small text-muted mt-3 mb-0">{{ t('otp.recoveryHint') }}</p>
@endsection
