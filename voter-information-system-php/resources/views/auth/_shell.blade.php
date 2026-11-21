<!DOCTYPE html>
<html lang="{{ \App\Support\Lang::current() }}" dir="{{ \App\Support\Lang::rtl() ? 'rtl' : 'ltr' }}">
<head>@include('layouts.head', ['title' => t('otp.title')])</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width:520px">
    <div class="text-center mb-3"><span class="fs-1 text-primary"><i class="bi bi-shield-lock"></i></span><h1 class="h4 mb-0">{{ t('app.name') }}</h1></div>
    <div class="card shadow-sm"><div class="card-body p-4">
        @if(session('error'))<div class="alert alert-danger py-2">{{ t(session('error')) }}</div>@endif
        @yield('card')
    </div></div>
    <form action="{{ url('/logout') }}" method="post" class="text-center mt-3">@csrf<button class="btn btn-link btn-sm">{{ t('otp.cancel') }}</button></form>
</div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ versioned_asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
