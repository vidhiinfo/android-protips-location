<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/app/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/app/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Voter System">
<title>{{ $title ?? '' }} | {{ t('app.name') }}</title>
<link rel="stylesheet" href="{{ asset($rtl ? 'vendor/bootstrap/css/bootstrap.rtl.min.css' : 'vendor/bootstrap/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/fonts.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
@stack('head')
