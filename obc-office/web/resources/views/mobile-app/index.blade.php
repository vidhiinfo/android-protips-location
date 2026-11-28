@extends('layouts.app', ['pageTitle' => 'Mobile App'])

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <div class="card-h">📱 Android app {!! $hasApk ? '<span class="badge-green">Available</span>' : '<span class="badge-amber">Not uploaded yet</span>' !!}</div>
            <div class="card-b">
                @if ($hasApk)
                    <div class="flex flex-wrap items-center gap-4">
                        <a href="{{ $downloadUrl }}" class="btn-primary">⬇ Download app (version {{ $version }})</a>
                        <span class="text-sm text-slate-500">{{ $sizeMb }} MB · uploaded {{ fmt_datetime($uploadedAt) }} · {{ number_format($downloads) }} downloads</span>
                    </div>
                    @if ($notes)<div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line"><b>What's new:</b><br>{{ $notes }}</div>@endif
                @else
                    <p class="text-sm text-slate-600">No app file has been uploaded yet.@if ($canManage) Upload the APK below. @endif</p>
                @endif
            </div>
        </div>

        <div class="card card-b text-sm text-slate-600">
            <div class="mb-2 font-semibold text-slate-800">How to install on Android</div>
            <ol class="list-inside list-decimal space-y-1">
                <li>Open the link (or scan the QR code) on the phone and tap <b>Download</b>.</li>
                <li>Open the downloaded file. If asked, allow <b>“Install unknown apps”</b> for your browser.</li>
                <li>Tap <b>Install</b>, open <b>OBC Office</b> and sign in with your portal username and password.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500">iPhone: the iOS app is distributed through the App Store / TestFlight, not by direct download.</p>
        </div>

        @if ($canManage)
            <form method="POST" enctype="multipart/form-data" action="{{ route('mobile-app.upload') }}" class="card">
                @csrf
                <div class="card-h">Upload a new version</div>
                <div class="card-b grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2"><label class="label">APK file *</label><input type="file" name="apk" accept=".apk" class="input" required></div>
                    <div><label class="label">Version *</label><input name="version" value="{{ old('version') }}" class="input" placeholder="e.g. 1.1.0" required></div>
                    <div class="sm:col-span-3"><label class="label">What's new (shown to users)</label><textarea name="notes" rows="3" class="input">{{ old('notes', $notes) }}</textarea></div>
                </div>
                <div class="border-t border-slate-100 px-4 py-3"><button class="btn-primary">Upload & publish</button>
                    <span class="ml-2 text-xs text-slate-500">Replaces the current file; the download link stays the same.</span></div>
            </form>
        @endif
    </div>

    <div class="space-y-6">
        <div class="card card-b text-center">
            <div class="font-semibold">Direct install link</div>
            @if ($isPublic)
                <div class="mx-auto mt-3 w-48" data-qr="{{ $publicUrl }}"></div>
                <input id="app-link" value="{{ $publicUrl }}" readonly class="input mt-3 text-center text-xs">
                <div class="mt-2 flex flex-wrap justify-center gap-2">
                    <button type="button" class="btn-secondary btn-sm" data-copy="#app-link">Copy link</button>
                    <a href="{{ $publicUrl }}" target="_blank" class="btn-secondary btn-sm">Open page</a>
                        <a href="https://wa.me/?text={{ rawurlencode('Install the '.(setting('org_short_name') ?: 'office').' app: '.$publicUrl) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">Share on WhatsApp</a>
                </div>
                <p class="mt-3 text-xs text-slate-500">Anyone with this link can download the app. They still need a portal account to sign in.</p>
            @else
                <p class="mt-3 text-sm text-slate-500">The public install page is <b>off</b>. Only signed-in users can download the app from this page.</p>
            @endif
        </div>

        @if ($canManage)
            <form method="POST" action="{{ route('mobile-app.settings') }}" class="card card-b space-y-3">
                @csrf
                <div class="font-semibold">Settings</div>
                <label class="flex items-start gap-2 text-sm"><input type="hidden" name="app_public" value="0"><input type="checkbox" name="app_public" value="1" class="mt-0.5" @checked($isPublic)>
                    <span>Public install page & direct download link<br><span class="text-xs text-slate-500">{{ $publicUrl }}</span></span></label>
                <input type="hidden" name="notes" value="{{ $notes }}">
                <button class="btn-primary btn-sm">Save</button>
            </form>
        @endif
    </div>
</div>
@endsection
