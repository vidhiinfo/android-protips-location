<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>@include('layouts.head', ['title' => t('app.install.title')])</head>
<body>
<div class="container px-3 py-4" style="max-width:760px">
    <div class="d-flex justify-content-end mb-2">@include('partials.lang', ['cls' => 'w-auto'])</div>
    <div class="d-flex align-items-center gap-3 mb-3">
        <img src="{{ asset(\App\Http\Controllers\SettingsController::appIcons()['192'] ?? 'images/app/icon-192.png') }}" alt="" width="72" height="72" style="border-radius:16px">
        <div><h1 class="h4 mb-0">{{ t('app.name') }}</h1><div class="text-muted">{{ t('app.install.subtitle') }}</div></div>
    </div>
    <div class="alert alert-success d-none" data-installed><i class="bi bi-check-circle"></i> {{ t('app.install.already') }}</div>

    <div class="card mb-3"><div class="card-body">
        <h2 class="h5"><i class="bi bi-android2 text-success"></i> Android</h2>
        <div class="d-flex flex-wrap gap-2 mb-2">
            @if($play)<a class="btn btn-dark" href="{{ $play }}"><i class="bi bi-google-play"></i> {{ t('app.install.play') }}</a>@endif
            @if($apk)<a class="btn btn-success" href="{{ asset($apk) }}" download><i class="bi bi-download"></i> {{ t('app.install.apk') }}</a>@endif
            <button type="button" class="btn btn-primary d-none" data-install-app><i class="bi bi-phone"></i> {{ t('app.install.now') }}</button>
        </div>
        <ol class="small mb-0">
            @if($apk)<li>{{ t('app.install.apkStep1') }}</li><li>{{ t('app.install.apkStep2') }}</li>@endif
            <li>{{ t('app.install.chromeStep') }}</li>
        </ol>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <h2 class="h5"><i class="bi bi-apple"></i> iPhone / iPad</h2>
        @if($appStore)<a class="btn btn-dark mb-2" href="{{ $appStore }}"><i class="bi bi-apple"></i> {{ t('app.install.appStore') }}</a>@endif
        <ol class="small mb-0">
            <li>{{ t('app.install.ios1') }}</li>
            <li>{!! t('app.install.ios2', '<i class="bi bi-box-arrow-up"></i>') !!}</li>
            <li>{{ t('app.install.ios3') }}</li>
        </ol>
    </div></div>

    <p class="small text-muted"><i class="bi bi-shield-lock"></i> {{ t('app.install.privacy') }}</p>
    <a class="btn btn-outline-primary" href="{{ url('/login') }}"><i class="bi bi-box-arrow-in-right"></i> {{ t('login.button') }}</a>
    @if(config('voters.branding.owner'))<p class="small text-muted text-center mt-3 mb-0">{{ t('brand.copyright', date('Y'), config('voters.branding.owner')) }}</p>@endif
</div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ versioned_asset('js/app.js') }}"></script>
</body>
</html>
