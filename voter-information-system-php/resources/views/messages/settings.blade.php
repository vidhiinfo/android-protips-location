@extends('layouts.app')
@section('title', t('msg.settings'))
@section('content')
<div class="container" style="max-width:1000px">
    @include('messages._banners')
    @if(session('testResult'))<div class="alert alert-info py-2">{{ session('testResult') }}</div>@endif
    <h4 class="mb-3"><i class="bi bi-sliders"></i> {{ t('msg.settings') }}</h4>
    <div class="card mb-3"><table class="table mb-0">
        @foreach([['SMS', $cfg['sms_provider'], $smsDryRun, $smsReady], ['WHATSAPP', $cfg['whatsapp_provider'], $waDryRun, $waReady]] as [$ch, $prov, $dry, $ready])
        <tr><th style="width:35%">{{ t('msg.channel.'.$ch) }}</th><td><code>{{ $prov }}</code>
            @if($dry)<span class="badge text-bg-warning">{{ t('msg.dryRun') }}</span>@else<span class="badge {{ $ready ? 'text-bg-success' : 'text-bg-danger' }}">{{ $ready ? t('msg.ready') : t('msg.notConfiguredShort') }}</span>@endif</td></tr>
        @endforeach
        <tr><th>{{ t('msg.sendWindow') }}</th><td>{{ $cfg['send_from'] }} - {{ $cfg['send_until'] }} ({{ $cfg['time_zone'] }}) <span class="badge {{ $windowOpen ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $windowOpen ? t('msg.open') : t('msg.closed') }}</span></td></tr>
        <tr><th>{{ t('msg.rate') }}</th><td>{{ $cfg['rate_per_second'] }} / s</td></tr>
        <tr><th>{{ t('msg.silenceRule') }}</th><td>{{ $cfg['enforce_silence_period'] ? t('admin.active') : t('admin.disabled') }}</td></tr>
    </table></div>
    <p class="small text-muted">{{ t('msg.settingsHelp') }}</p>
    <div class="card"><div class="card-header fw-semibold">{{ t('msg.testSend') }}</div>
        <form method="post" action="{{ url('/messages/settings/test') }}" class="card-body row g-2 align-items-end">@csrf
            <div class="col-md-4"><label class="form-label small">{{ t('col.mobile') }}</label><input name="mobile" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label small">{{ t('msg.template') }}</label><select name="templateId" class="form-select">
                <optgroup label="{{ t('msg.channel.SMS') }}">@foreach($templatesSms as $tp)<option value="{{ $tp->id }}">[{{ $tp->language }}] {{ $tp->name }}</option>@endforeach</optgroup>
                <optgroup label="{{ t('msg.channel.WHATSAPP') }}">@foreach($templatesWa as $tp)<option value="{{ $tp->id }}">[{{ $tp->language }}] {{ $tp->name }}</option>@endforeach</optgroup></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">{{ t('msg.sendTest') }}</button></div>
        </form></div>
</div>
@endsection
