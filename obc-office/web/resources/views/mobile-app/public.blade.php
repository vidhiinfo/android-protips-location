<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install the app · {{ setting('org_short_name') ?: setting('org_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --color-brand: {{ setting('theme_color', '#ea580c') }}; }</style>
</head>
@php $leaderPhoto = branding_image_url('leader'); @endphp
<body class="min-h-screen bg-slate-100 p-4">
<div class="mx-auto max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
    <div class="bg-brand px-6 py-8 text-center text-white">
        @if (filled(setting('leader_name')))
            <div class="mx-auto h-32 w-32 overflow-hidden rounded-full bg-white/20 ring-4 ring-white/80">
                @if ($leaderPhoto)<img src="{{ $leaderPhoto }}" alt="" class="h-full w-full object-cover object-top">@endif
            </div>
            <div class="mt-4 text-xl font-bold">{{ setting('leader_name') }}</div>
            @foreach (leader_designations() as $line)<div class="text-sm text-white/90">{{ $line }}</div>@endforeach
        @endif
        <div class="mt-4 text-sm font-medium text-white/80">{{ setting('org_name') }}</div>
    </div>
    <div class="p-6 text-center">
        <h1 class="text-lg font-semibold text-slate-900">📱 Office mobile app</h1>
        @if ($hasApk)
            <p class="mt-1 text-sm text-slate-500">Android · version {{ $version }} · {{ $sizeMb }} MB</p>
            <a href="{{ $downloadUrl }}" class="btn-primary mt-5 w-full py-3 text-base">⬇ Download for Android</a>
            @if ($notes)<p class="mt-4 rounded-lg bg-slate-50 p-3 text-left text-sm whitespace-pre-line"><b>What's new:</b> {{ $notes }}</p>@endif
            <ol class="mt-5 list-inside list-decimal space-y-1 text-left text-sm text-slate-600">
                <li>Tap <b>Download for Android</b>.</li>
                <li>Open the file. If asked, allow <b>“Install unknown apps”</b>.</li>
                <li>Tap <b>Install</b>, then sign in with your portal account.</li>
            </ol>
            <div class="mt-6 hidden sm:block">
                <p class="text-xs text-slate-500">On a computer? Scan with your phone:</p>
                <div class="mx-auto mt-2 w-40" data-qr="{{ $publicUrl }}"></div>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">The app will be available here soon.</p>
        @endif
        <p class="mt-6 text-xs text-slate-400">iPhone users: please contact the office for the iOS version.</p>
    </div>
</div>
<p class="mt-4 text-center text-xs text-slate-400">{{ setting('footer_text') }}</p>
</body>
</html>
