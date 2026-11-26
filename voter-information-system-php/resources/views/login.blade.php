<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>@include('layouts.head', ['title' => t('login.title')])</head>
@php($ls = config('voters.branding.level') === 'loksabha')
<body class="login-page">
<div class="container-fluid p-0"><div class="row g-0 min-vh-100">
    <div class="col-lg-7 login-banner {{ $ls ? 'login-banner-ls' : '' }} d-flex flex-column">
        <div class="login-banner-inner flex-grow-1 d-flex flex-column justify-content-center">
            <div class="d-flex align-items-center gap-2 mb-4 login-brand">
                <span class="login-brand-mark"><i class="bi bi-person-vcard"></i></span>
                <span><span class="d-block fw-semibold">{{ t('app.name') }}</span>
                    @if(config('voters.branding.owner'))<span class="d-block small opacity-75">{{ t('brand.by', config('voters.branding.owner')) }}</span>@endif
</span>
            </div>
            @if($branding['organisation'] || !empty($branding['rep_photo']))
            {{-- representative (MP / MLA): photo in a circle, or a placeholder until one is uploaded in Admin > Settings --}}
            <div class="login-rep mb-4">
                <span class="login-rep-photo">@if(!empty($branding['rep_photo']))<img src="{{ asset(ltrim($branding['rep_photo'], '/')) }}" alt="{{ $branding['organisation'] }}">@else<i class="bi bi-person-fill" aria-hidden="true"></i>@endif</span>
                @if($branding['organisation'])<span class="login-rep-name">{{ $branding['organisation'] }}</span>@endif
            </div>
            @endif
            @if($ls || config('voters.branding.constituency'))
            <div class="login-level mb-3"><span class="login-level-tag"><i class="bi bi-{{ $ls ? 'bank2' : 'building' }}"></i> {{ t($ls ? 'login.level.loksabha' : 'login.level.assembly') }}</span>
                @if(config('voters.branding.constituency'))<span class="login-level-name">{{ config('voters.branding.constituency') }}</span>@endif
                @if($ls && count($loginAssemblies))<span class="login-level-count">{{ t('login.level.segments', count($loginAssemblies)) }}</span>@endif</div>
            @endif
            <h1 class="login-title">{{ t($ls ? 'login.banner.titleLs' : 'login.banner.title') }}</h1>
            <p class="login-tagline">{{ t($ls ? 'login.banner.taglineLs' : 'login.banner.tagline') }}</p>
            <img class="login-illustration d-none d-lg-block" src="{{ asset(ltrim($branding['banner_image'], '/')) }}" alt="" aria-hidden="true">
            <ul class="login-points list-unstyled d-none d-lg-block mb-0">
                <li><i class="bi bi-translate"></i> <span>{{ t('login.banner.point1') }}</span></li>
                <li><i class="bi bi-bar-chart-line"></i> <span>{{ t('login.banner.point2') }}</span></li>
                <li><i class="bi bi-chat-dots"></i> <span>{{ t('login.banner.point3') }}</span></li>
            </ul>
        </div>
        <div class="login-stripe" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>
    <div class="col-lg-5 d-flex flex-column login-form-col">
        <div class="d-flex justify-content-end p-3">@include('partials.lang', ['cls' => 'lang-login'])</div>
        <div class="flex-grow-1 d-flex align-items-center justify-content-center px-3 pb-5">
            <div class="login-card w-100">
                @if($branding['logo'])<img src="{{ asset(ltrim($branding['logo'], '/')) }}" alt="" class="login-logo mb-3">@endif
                <h2 class="h3 mb-1">{{ t('login.welcome') }}</h2>
                <p class="text-muted mb-4">{{ t('login.subtitle') }}</p>
                <div class="alert alert-danger py-2 d-none" data-cookie-warning><i class="bi bi-exclamation-octagon"></i> {{ t('login.cookies') }}</div>
                @if(request('error') === 'expired')<div class="alert alert-warning py-2"><i class="bi bi-clock-history"></i> {{ t('login.error.expired') }}</div>
                @elseif(request('error') === 'otp')<div class="alert alert-danger py-2"><i class="bi bi-shield-x"></i> {{ t('otp.error.locked') }}</div>
                @elseif(request('error') === 'assembly')<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> {{ t('login.error.assembly') }}</div>
                @elseif(request('error'))<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> {{ t('login.error') }}</div>@endif
                @if(request('logout'))<div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> {{ t('login.loggedOut') }}</div>@endif
                <form action="{{ url('/login') }}" method="post">@csrf
                    <div class="mb-3"><label class="form-label fw-medium" for="username">{{ t('login.username') }}</label>
                        <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input id="username" name="username" class="form-control" required autofocus autocomplete="username"></div></div>
                    <div class="mb-3"><label class="form-label fw-medium" for="password">{{ t('login.password') }}</label>
                        <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password"></div></div>
                    <div class="mb-4"><label class="form-label fw-medium" for="assemblyNo">{{ t($ls ? 'login.segment' : 'login.assembly') }}</label>
                        <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                            <select id="assemblyNo" name="assemblyNo" class="form-select">
                                <option value="">{{ t($ls ? 'login.allSegments' : 'login.allAssemblies') }}</option>
                                @foreach($loginAssemblies as $no => $name)<option value="{{ $no }}">{{ $no }} - {{ $name }}</option>@endforeach
                            </select></div>
                        <div class="form-text">{{ t('login.assemblyHelp') }}</div></div>
                    <button class="btn btn-primary btn-lg w-100 login-btn"><i class="bi bi-box-arrow-in-right"></i> {{ t('login.button') }}</button>
                </form>
                <p class="small text-muted mt-4 mb-0 d-flex gap-2"><i class="bi bi-shield-lock"></i> <span>{{ t('login.secure') }}</span></p>
                <a class="small d-inline-flex gap-1 mt-2 native-hide" href="{{ url('/mobile-app') }}"><i class="bi bi-phone"></i> {{ t('app.install.link') }}</a>
                @if(config('voters.branding.owner'))<p class="small text-muted mt-3 mb-0">{{ t('brand.copyright', date('Y'), config('voters.branding.owner')) }}</p>@endif
            </div>
        </div>
    </div>
</div></div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ versioned_asset('js/app.js') }}"></script>
</body>
</html>
