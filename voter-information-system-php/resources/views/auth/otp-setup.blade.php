@extends($pending ? 'auth._shell' : 'layouts.app')
@section($pending ? 'card' : 'content')
@unless($pending)<div class="container" style="max-width:620px">@include('partials.flash')<div class="card"><div class="card-body p-4">@endunless
<h2 class="h5">{{ t('otp.setupTitle') }}</h2>
@if($pending)<div class="alert alert-info py-2 small">{{ t('otp.setupRequired') }}</div>@endif
@if(!$setup)
    <p class="text-muted small">{{ t('otp.setupIntro') }}</p>
    <form method="post" action="{{ url($pending ? '/login/otp/setup' : '/account/security/setup') }}">@csrf
        @foreach($methods as $m)
        <div class="form-check border rounded p-3 ps-5 mb-2"><input class="form-check-input" type="radio" name="method" id="m-{{ $m }}" value="{{ $m }}" @checked($loop->first)>
            <label class="form-check-label" for="m-{{ $m }}"><b>{{ t('otp.method.' . $m) }}</b><div class="small text-muted">{{ t('otp.method.' . $m . '.help') }}</div></label></div>
        @endforeach
        @if(count($methods) > 1)<label class="form-label small mt-2 mb-0">{{ t('col.mobile') }}</label>
            <input name="mobile" class="form-control mb-1" inputmode="tel" value="{{ $u->mobile }}" placeholder="98XXXXXXXX"><div class="form-text mb-2">{{ t('otp.mobileHelp') }}</div>@endif
        <button class="btn btn-primary w-100 mt-2">{{ t('otp.next') }}</button>
    </form>
@else
    @if($setup['method'] === 'app')
        <ol class="small">
            <li>{{ t('otp.app1') }}</li>
            <li>{{ t('otp.app2') }}</li>
        </ol>
        <div class="text-center my-3"><div id="otp-qr" data-uri="{{ $uri }}" class="d-inline-block p-2 bg-white border rounded"></div></div>
        <p class="small text-center">{{ t('otp.manual') }}<br><code class="user-select-all fs-6">{{ trim(chunk_split($setup['secret'], 4, ' ')) }}</code></p>
        <p class="small">{{ t('otp.app3') }}</p>
    @else
        <p class="small">{{ t('otp.smsSent', '••••••' . substr($setup['mobile'], -4)) }}</p>
    @endif
    <form method="post" action="{{ url($pending ? '/login/otp/confirm' : '/account/security/confirm') }}">@csrf
        <input name="code" class="form-control form-control-lg text-center mb-3" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus placeholder="123456" style="letter-spacing:.3em">
        <button class="btn btn-success w-100"><i class="bi bi-shield-check"></i> {{ t('otp.confirm') }}</button>
    </form>
@endif
@unless($pending)</div></div></div>@endunless
@endsection
@push('head')
<script src="{{ asset('vendor/qrcode/qrcode.js') }}"></script>
@endpush
