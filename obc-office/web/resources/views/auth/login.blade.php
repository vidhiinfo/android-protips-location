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
@php
    $showLeader = setting('leader_on_login') && filled(setting('leader_name'));
    $leaderPhoto = branding_image_url('leader');
    $logo = branding_image_url('logo');
@endphp
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
<div class="w-full {{ $showLeader ? 'max-w-4xl' : 'max-w-sm' }}">
    <div class="{{ $showLeader ? 'grid overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 md:grid-cols-2' : '' }}">

        @if ($showLeader)
            {{-- Leader panel --}}
            <div class="relative flex flex-col items-center justify-center bg-brand px-6 py-8 text-center text-white md:py-12">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/10 to-black/20"></div>
                @if ($logo)
                    <img src="{{ $logo }}" alt="" class="relative mb-4 h-12 w-12 rounded-full bg-white object-contain p-1 shadow">
                @endif
                <div class="relative h-40 w-40 overflow-hidden rounded-full bg-white/20 shadow-lg ring-4 ring-white/80 md:h-52 md:w-52">
                    @if ($leaderPhoto)
                        <img src="{{ $leaderPhoto }}" alt="{{ setting('leader_name') }}" class="h-full w-full object-cover object-top">
                    @else
                        <div class="flex h-full items-center justify-center text-5xl font-bold text-white/90">{{ mb_substr(trim(str_replace(['श्री', 'Shri'], '', setting('leader_name'))), 0, 1) }}</div>
                    @endif
                </div>
                <h1 class="relative mt-5 text-2xl font-bold leading-tight">{{ setting('leader_name') }}</h1>
                @foreach (leader_designations() as $line)
                    <p class="relative mt-1 text-sm font-medium text-white/90 md:text-base">{{ $line }}</p>
                @endforeach
            </div>
        @endif

        {{-- Sign-in form --}}
        <div class="{{ $showLeader ? 'p-6 sm:p-10' : '' }}">
            <div class="mb-6 text-center">
                @unless ($showLeader)
                    @if ($logo)
                        <img src="{{ $logo }}" alt="" class="mx-auto h-20 w-20 rounded-full bg-white object-contain shadow">
                    @else
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand text-2xl font-bold text-white shadow">{{ mb_substr(setting('org_short_name') ?: setting('org_name'), 0, 1) }}</div>
                    @endif
                @endunless
                <h2 class="{{ $showLeader ? '' : 'mt-4' }} text-xl font-semibold text-slate-900">{{ setting('org_name') }}</h2>
                <p class="text-sm text-slate-500">Sign in to continue</p>
            </div>
            <form method="POST" action="{{ url('/login') }}" class="{{ $showLeader ? 'space-y-4' : 'card card-b space-y-4' }}">
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
            <p class="mt-6 text-center text-xs text-slate-400">{{ setting('footer_text') }}</p>
        </div>
    </div>
</div>
</body>
</html>
