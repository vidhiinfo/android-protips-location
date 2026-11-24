<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ setting('org_name') }}</title>
    @vite(['resources/css/app.css'])
    <style>:root { --color-brand: {{ setting('theme_color', '#ea580c') }}; }</style>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        @if (setting('logo_path'))
            <img src="{{ asset('storage/'.setting('logo_path')) }}" alt="" class="mx-auto h-20 w-20 rounded-full bg-white object-contain shadow">
        @else
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand text-2xl font-bold text-white shadow">{{ mb_substr(setting('org_short_name') ?: setting('org_name'), 0, 1) }}</div>
        @endif
        <h1 class="mt-4 text-xl font-semibold text-slate-900">{{ setting('org_name') }}</h1>
        <p class="text-sm text-slate-500">Office of the {{ setting('president_name') }}</p>
    </div>
    <form method="POST" action="{{ url('/login') }}" class="card card-b space-y-4">
        @csrf
        <div>
            <label class="label" for="username">Username or email</label>
            <input id="username" name="username" value="{{ old('username') }}" class="input" required autofocus autocomplete="username">
            @error('username')<p class="err">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded"> Keep me signed in</label>
        <button class="btn-primary w-full">Sign in</button>
    </form>
    <p class="mt-4 text-center text-xs text-slate-400">{{ setting('footer_text') }}</p>
</div>
</body>
</html>
