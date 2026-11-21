@extends('layouts.app')
@section('title', t('msg.new'))
@section('content')
<div class="container" style="max-width:1100px">
    @include('messages._banners')
    <h4 class="mb-3"><i class="bi bi-send"></i> {{ t('msg.new') }}</h4>
    @if($dryRun)<div class="alert alert-warning py-2"><i class="bi bi-cone-striped"></i> {{ t('msg.dryRunBanner') }}</div>
    @elseif(!$ready)<div class="alert alert-danger py-2">{{ t('msg.notConfigured') }}</div>@endif
    <form method="get" class="card card-body mb-3">
        @foreach($f->hiddenParams() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">{{ t('msg.channel') }}</label>
                <select name="channel" class="form-select" data-autosubmit>@foreach($channels as $c)<option value="{{ $c }}" @selected($c === $channel)>{{ t('msg.channel.'.$c) }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label small">{{ t('msg.template') }}</label>
                <select name="templateId" class="form-select" data-autosubmit>
                    @if($templates->isEmpty())<option value="">{{ t('msg.noTemplates') }}</option>@endif
                    @foreach($templates as $tp)<option value="{{ $tp->id }}" @selected($template && $tp->id === $template->id)>[{{ $tp->language }}] {{ $tp->name }}</option>@endforeach</select></div>
            <div class="col-md-3 small text-muted">{{ t('msg.recipientsFrom') }} <a href="{{ url('/voters') }}?{{ $f->queryWithSort() }}">{{ $f->active() ? t('list.filtered') : t('common.all') }}</a></div>
        </div>
    </form>
    @if($preview)
    <div class="row g-2 mb-3">
        @foreach(['voters' => 'msg.p.voters', 'recipients' => 'msg.p.recipients', 'noMobile' => 'msg.p.noMobile', 'optedOut' => 'msg.p.optedOut', 'noConsent' => 'msg.p.noConsent', 'duplicates' => 'msg.p.duplicates'] as $k => $label)
        <div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t($label) }}</div><div class="num {{ $k === 'recipients' ? 'text-primary' : '' }}">{{ $preview[$k] ?? 0 }}</div></div></div>
        @endforeach
        @if($channel === 'SMS')<div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t('msg.p.segments') }}</div><div class="num">{{ $preview['maxSegments'] }}</div></div></div>@endif
    </div>
    <div class="card mb-3"><div class="card-header fw-semibold">{{ t('msg.preview') }}</div><ul class="list-group list-group-flush">
        @forelse($preview['samples'] as $s)
            <li class="list-group-item"><div class="small text-muted">{{ $s[0] }} &middot; {{ $s[1] }}</div><div dir="auto" style="white-space:pre-wrap">{{ $s[2] }}</div></li>
        @empty <li class="list-group-item">{{ t('msg.error.noRecipients') }}</li> @endforelse
    </ul></div>
    @if($preview['recipients'] > 0)
    <form method="post" action="{{ url('/messages/campaigns') }}" class="card card-body" data-confirm="{{ t('msg.confirmSend', $preview['recipients']) }}">@csrf
        @foreach($f->hiddenParams() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <input type="hidden" name="templateId" value="{{ $template->id }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label small">{{ t('msg.campaignName') }}</label><input name="campaignName" class="form-control" maxlength="150" dir="auto" placeholder="{{ $template->name }}"></div>
            <div class="col-md-4"><label class="form-label small">{{ t('msg.scheduleAt') }}</label><input name="scheduledAt" type="datetime-local" class="form-control"></div>
            <div class="col-md-3"><button class="btn btn-primary w-100" @disabled($silence || (!$dryRun && !$ready))><i class="bi bi-send"></i> {{ t('msg.send', $preview['recipients']) }}</button></div>
        </div>
        <div class="form-text">{{ t('msg.rulesHint') }}</div>
    </form>
    @endif
    @endif
</div>
@endsection
