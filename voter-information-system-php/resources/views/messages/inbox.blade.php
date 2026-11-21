@extends('layouts.app')
@section('title', t('inbox.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-inbox"></i> {{ t('inbox.title') }}</h4><div class="small text-muted">{{ t('inbox.intro') }}</div></div>
        <form method="get" action="{{ url('/messages/inbox') }}" class="d-flex gap-2 align-items-end">
            <input name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="{{ t('inbox.search') }}">
            <select name="status" class="form-select form-select-sm" data-autosubmit>@foreach(['OPEN', 'CLOSED', 'ALL'] as $s)<option value="{{ $s }}" @selected($s === $status)>{{ t('inbox.status.' . $s) }}</option>@endforeach</select>
        </form>
    </div>
    @unless($hooksOn)<div class="alert alert-info small py-2">{{ t('inbox.setup') }}</div>@endunless
    <div class="row g-3">
        <div class="col-md-4 {{ $conv ? 'd-none d-md-block' : '' }}">
            <div class="list-group" style="max-height:72vh;overflow:auto">
                @forelse($list as $c)
                <a href="{{ url('/messages/inbox/' . $c->id) }}?status={{ $status }}" class="list-group-item list-group-item-action {{ $conv && $conv->id === $c->id ? 'active' : '' }}">
                    <div class="d-flex gap-2"><span class="fw-semibold me-auto text-truncate">{{ $c->name ?: \App\Services\Messaging\MessagingService::mask($c->mobile) }}</span>
                        @if($c->unread)<span class="badge text-bg-danger">{{ $c->unread }}</span>@endif</div>
                    <div class="small {{ $conv && $conv->id === $c->id ? '' : 'text-muted' }}"><i class="bi bi-{{ $c->channel === 'WHATSAPP' ? 'whatsapp' : 'chat-text' }}"></i> {{ \App\Services\Messaging\MessagingService::mask($c->mobile) }} · {{ $c->last_at ? substr($c->last_at, 0, 16) : '' }}</div>
                </a>
                @empty
                <div class="list-group-item text-muted">{{ t('inbox.none') }}</div>
                @endforelse
            </div>
        </div>
        <div class="col-md-8">
            @if($conv)
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ url('/messages/inbox') }}" class="btn btn-sm btn-outline-secondary d-md-none"><i class="bi bi-chevron-left"></i></a>
                    <div class="me-auto"><span class="fw-semibold">{{ $conv->name ?: t('inbox.unknown') }}</span>
                        <span class="small text-muted">{{ \App\Services\Messaging\MessagingService::mask($conv->mobile) }} · {{ t('msg.channel.' . $conv->channel) }}</span>
                        @if($voter) · <a class="small" href="{{ url('/voters/' . $voter->id) }}">{{ $voter->epic_no }} · {{ t('col.boothNo') }} {{ $voter->booth_no }}</a>@endif</div>
                    <form method="post" action="{{ url('/messages/inbox/' . $conv->id . '/status') }}">@csrf
                        <input type="hidden" name="status" value="{{ $conv->status === 'OPEN' ? 'CLOSED' : 'OPEN' }}">
                        <button class="btn btn-sm btn-outline-secondary">{{ $conv->status === 'OPEN' ? t('inbox.close') : t('inbox.reopen') }}</button></form>
                </div>
                <div class="card-body" style="max-height:55vh;overflow:auto;background:#f0f2f5" data-scroll-end>
                    @foreach($messages as $m)
                    <div class="d-flex mb-2 {{ $m->direction === 'IN' ? '' : 'justify-content-end' }}">
                        <div class="p-2 rounded shadow-sm small {{ $m->status === 'NOTE' ? 'bg-warning-subtle' : ($m->direction === 'IN' ? 'bg-white' : 'bg-success-subtle') }}" style="max-width:80%">
                            <div dir="auto" style="white-space:pre-wrap">{{ $m->body }}@if($m->media_type) <span class="badge text-bg-light border"><i class="bi bi-paperclip"></i> {{ $m->media_type }}</span>@endif</div>
                            <div class="text-muted text-end" style="font-size:.75rem">{{ substr($m->at, 11, 5) }} {{ substr($m->at, 0, 10) }}@if($m->by_user) · {{ $m->by_user }}@endif
                                @if($m->direction === 'OUT' && $m->status && $m->status !== 'NOTE') · <span class="{{ $m->status === 'FAILED' ? 'text-danger' : ($m->status === 'READ' ? 'text-primary' : '') }}" title="{{ $m->error }}">{{ t('inbox.st.' . $m->status) }}</span>@endif</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <form method="post" action="{{ url('/messages/inbox/' . $conv->id . '/reply') }}" class="card-footer">@csrf
                    @if($windowOpen)
                    <div class="d-flex gap-2"><textarea name="body" class="form-control" rows="2" maxlength="1000" dir="auto" placeholder="{{ t('inbox.typeReply') }}">{{ old('body') }}</textarea>
                        <button class="btn btn-success"><i class="bi bi-send"></i></button></div>
                    <details class="small mt-1"><summary>{{ t('inbox.orTemplate') }}</summary>
                    @endif
                    @unless($windowOpen)<div class="small text-muted mb-1">{{ t('inbox.windowClosed') }}</div>@endunless
                    <div class="d-flex gap-2 mt-1"><select name="template_id" class="form-select form-select-sm"><option value="">--</option>
                        @foreach($templates as $tp)<option value="{{ $tp->id }}">{{ $tp->name }} ({{ $tp->language }})</option>@endforeach</select>
                        <button class="btn btn-sm btn-outline-success text-nowrap" name="useTemplate" value="1">{{ t('inbox.sendTemplate') }}</button></div>
                    @if($windowOpen)</details>@endif
                </form>
            </div>
            @else
            <div class="text-muted text-center py-5">{{ t('inbox.pick') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
