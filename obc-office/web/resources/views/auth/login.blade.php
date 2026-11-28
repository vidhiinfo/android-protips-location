<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · {{ setting('org_name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        :root { --color-brand: {{ setting('theme_color', '#ea580c') }}; }
        .login-pattern { background-image: radial-gradient(rgba(255,255,255,.14) 1px, transparent 1px); background-size: 22px 22px; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .rise { animation: rise .6s ease-out both; }
        .rise-2 { animation-delay: .12s; } .rise-3 { animation-delay: .24s; }
        @media (prefers-reduced-motion: reduce) { .rise { animation: none; } }
    </style>
</head>
@php
    $showLeader = setting('leader_on_login') && filled(setting('leader_name'));
    $leaderPhoto = branding_image_url('leader');
    $background = branding_image_url('login-bg');
    $logo = branding_image_url('logo');
    $appLink = setting('app_public') && setting('app_apk_path') ? route('app.public') : null;
    $initial = mb_substr(trim(str_replace(['श्री', 'Shri', 'Shri.'], '', (string) setting('leader_name'))), 0, 1);
@endphp
<body class="min-h-screen bg-white text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-[1.15fr_1fr]">

    {{-- ===== Left / top: leader & organisation ===== --}}
    <section class="relative isolate flex flex-col overflow-hidden bg-brand text-white">
        @if ($background)
            <img src="{{ $background }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover">
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-black/45 via-black/35 to-black/70"></div>
        @else
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-white/15 via-transparent to-black/35"></div>
            <div class="login-pattern absolute inset-0 -z-10 opacity-70"></div>
            <div class="absolute -top-32 -right-32 -z-10 h-96 w-96 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-40 -left-24 -z-10 h-[28rem] w-[28rem] rounded-full bg-black/15 blur-2xl"></div>
        @endif

        {{-- top bar --}}
        <header class="flex items-center gap-3 px-6 pt-6 sm:px-10 sm:pt-8">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="h-11 w-11 rounded-full bg-white object-contain p-1 shadow-md">
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 text-lg font-bold ring-1 ring-white/40">{{ mb_substr(setting('org_short_name') ?: setting('org_name'), 0, 1) }}</span>
            @endif
            <span class="text-sm font-semibold tracking-wide text-white/90 sm:text-base">{{ setting('org_name') }}</span>
        </header>

        {{-- leader --}}
        <div class="flex flex-1 flex-col items-center justify-center px-6 py-10 text-center sm:px-10 lg:py-6">
            @if ($showLeader)
                <div class="rise relative">
                    <div class="absolute -inset-3 rounded-[2.25rem] bg-white/20 blur-md"></div>
                    <div class="relative aspect-[4/5] h-56 overflow-hidden rounded-[2rem] bg-white/20 shadow-2xl ring-4 ring-white/90 sm:h-72 lg:h-[min(26rem,42vh)]">
                        @if ($leaderPhoto)
                            <img src="{{ $leaderPhoto }}" alt="{{ setting('leader_name') }}" class="h-full w-full object-cover object-top">
                        @else
                            <div class="flex h-full items-center justify-center text-7xl font-bold text-white/90">{{ $initial }}</div>
                        @endif
                    </div>
                </div>
                <h1 class="rise rise-2 mt-8 text-3xl font-extrabold leading-tight drop-shadow sm:text-4xl lg:mt-6 xl:text-5xl">{{ setting('leader_name') }}</h1>
                <div class="rise rise-2 mt-3 flex flex-col items-center gap-2">
                    @foreach (leader_designations() as $line)
                        <span class="rounded-full bg-white/15 px-4 py-1.5 text-sm font-medium ring-1 ring-white/25 backdrop-blur-sm sm:text-base">{{ $line }}</span>
                    @endforeach
                </div>
                @if (filled(setting('login_tagline')))
                    <p class="rise rise-3 mt-6 max-w-xl lg:mt-4 text-lg font-semibold text-white/95 italic sm:text-xl">“{{ setting('login_tagline') }}”</p>
                @endif
            @else
                <div class="rise flex h-28 w-28 items-center justify-center rounded-full bg-white/15 ring-4 ring-white/70">
                    @if ($logo)<img src="{{ $logo }}" alt="" class="h-20 w-20 object-contain">@else<span class="text-5xl font-bold">{{ mb_substr(setting('org_short_name') ?: setting('org_name'), 0, 1) }}</span>@endif
                </div>
                <h1 class="rise rise-2 mt-6 text-3xl font-extrabold sm:text-4xl">{{ setting('org_name') }}</h1>
            @endif
        </div>

        <footer class="hidden px-10 pb-6 text-xs text-white/70 lg:block">© {{ date('Y') }} {{ setting('org_name') }}</footer>
    </section>

    {{-- ===== Right / bottom: sign in ===== --}}
    <section class="relative flex items-center justify-center bg-slate-50 px-6 py-10 sm:px-12 lg:bg-white">
        <div class="absolute inset-x-0 top-0 h-1.5 bg-brand lg:hidden"></div>
        <div class="w-full max-w-md">
            <div class="rise">
                <p class="text-sm font-semibold tracking-wider text-brand uppercase">Office Portal</p>
                <h2 class="mt-2 text-3xl font-bold text-slate-900">स्वागत है 🙏</h2>
                <p class="mt-1 text-slate-500">Welcome — please sign in with your office account.</p>
            </div>

            <form method="POST" action="{{ url('/login') }}" class="rise rise-2 mt-8 space-y-5">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="username">Username or email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                               class="block w-full rounded-xl border-0 bg-white py-3 pr-4 pl-11 text-base text-slate-900 shadow-sm ring-1 ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-brand focus:outline-none @error('username') ring-red-400 @enderror"
                               placeholder="e.g. obcadmin">
                    </div>
                    @error('username')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="password">Password</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        </span>
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                               class="block w-full rounded-xl border-0 bg-white py-3 pr-12 pl-11 text-base text-slate-900 shadow-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand focus:outline-none">
                        <button type="button" id="toggle-pw" class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 hover:text-slate-600" aria-label="Show password">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 accent-[var(--color-brand)]"> Keep me signed in</label>
                <button class="w-full rounded-xl bg-brand py-3.5 text-base font-semibold text-white shadow-lg shadow-black/10 transition hover:brightness-110 focus:ring-2 focus:ring-brand focus:ring-offset-2 focus:outline-none">Sign in</button>
            </form>

            @if ($appLink)
                <a href="{{ $appLink }}" class="rise rise-3 mt-8 flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand hover:shadow-md">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-2xl">📱</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-slate-900">Get the mobile app</span>
                        <span class="block text-sm text-slate-500">Install on your Android phone{{ setting('app_version') ? ' · v'.setting('app_version') : '' }}</span>
                    </span>
                    <span class="text-slate-400">→</span>
                </a>
            @endif

            <p class="mt-10 text-center text-xs text-slate-400">{{ setting('footer_text') }}</p>
        </div>
    </section>
</div>
<script>
    document.getElementById('toggle-pw')?.addEventListener('click', () => {
        const pw = document.getElementById('password');
        pw.type = pw.type === 'password' ? 'text' : 'password';
    });
</script>
</body>
</html>
