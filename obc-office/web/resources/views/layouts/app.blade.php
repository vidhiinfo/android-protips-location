<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($pageTitle) ? $pageTitle.' · ' : '' }}{{ setting('org_short_name') ?: setting('org_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --color-brand: {{ setting('theme_color', '#ea580c') }}; }</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
@php
    $user = auth()->user();
    $navGroups = collect(config('office.modules'))
        ->filter(fn ($m, $key) => $user->canView($key))
        ->groupBy('group', true);
@endphp

<aside id="sidebar" class="no-print fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 transition lg:translate-x-0">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
        @if (setting('logo_path'))
            <img src="{{ asset('storage/'.setting('logo_path')) }}" alt="" class="h-10 w-10 rounded-full bg-white object-contain">
        @else
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand text-lg font-bold text-white">{{ mb_substr(setting('org_short_name') ?: setting('org_name'), 0, 1) }}</span>
        @endif
        <span class="min-w-0">
            <span class="block truncate text-sm font-semibold text-white">{{ setting('org_short_name') ?: setting('org_name') }}</span>
            <span class="block truncate text-xs text-slate-400">{{ setting('president_name') }}</span>
        </span>
    </a>
    <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4">
        @foreach ($navGroups as $group => $modules)
            <div>
                <div class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $group }}</div>
                @foreach ($modules as $key => $m)
                    @php $base = \Illuminate\Support\Str::beforeLast($m['route'], '.'); @endphp
                    <a href="{{ route($m['route']) }}"
                       class="nav-link {{ request()->routeIs($m['route']) || (str_contains($m['route'], '.') && request()->routeIs($base.'.*')) ? 'active' : '' }}">
                        <span class="w-5 text-center">{{ $m['icon'] }}</span> {{ $m['label'] }}
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
    <div class="border-t border-white/10 p-3 text-xs text-slate-400">
        <div class="truncate font-medium text-slate-200">{{ $user->name }}</div>
        <div class="truncate">{{ $user->roleLabel() }} · {{ $user->jurisdictionLabel() }}</div>
        <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf
            <button class="text-slate-300 hover:text-white">Sign out →</button>
        </form>
    </div>
</aside>

<div class="lg:pl-64">
    <header class="no-print sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur">
        <button id="nav-toggle" class="rounded-md p-1.5 ring-1 ring-slate-300 lg:hidden" aria-label="Menu">☰</button>
        <h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-slate-900">{{ $pageTitle ?? '' }}</h1>
        @yield('actions')
    </header>

    <main class="mx-auto max-w-7xl p-4 sm:p-6">
        @if (session('ok'))
            <div class="no-print mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">{{ session('ok') }}</div>
        @endif
        @if ($errors->any() && ! ($hideErrorSummary ?? false))
            <div class="no-print mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                <div class="font-medium">Please check the form:</div>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="no-print px-6 pb-6 text-center text-xs text-slate-400">{{ setting('footer_text') }}</footer>
</div>
</body>
</html>
