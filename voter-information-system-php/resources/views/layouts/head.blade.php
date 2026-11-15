<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? '' }} | {{ t('app.name') }}</title>
<link rel="stylesheet" href="{{ asset($rtl ? 'vendor/bootstrap/css/bootstrap.rtl.min.css' : 'vendor/bootstrap/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/fonts.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
