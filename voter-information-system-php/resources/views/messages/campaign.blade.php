@extends('layouts.app')
@section('title', $c->name)
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('messages._banners')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h4 class="me-auto mb-0"><span dir="auto">{{ $c->name }}</span> <span class="badge text-bg-light border fs-6">{{ t('msg.c.'.$c->status) }}</span></h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/messages/campaigns/'.$c->id) }}"><i class="bi bi-arrow-clockwise"></i> {{ t('msg.refresh') }}</a>
        @if(in_array($c->status, ['QUEUED', 'SENDING']))<form method="post" action="{{ url('/messages/campaigns/'.$c->id.'/cancel') }}" data-confirm="{{ t('msg.confirmCancel') }}">@csrf
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-stop-circle"></i> {{ t('msg.cancel') }}</button></form>@endif
    </div>
    <div class="small text-muted mb-3">{{ t('msg.channel.'.$c->channel) }} &middot; {{ $c->template?->name }} @if($c->filter_summary) &middot; {{ $c->filter_summary }} @endif
        @if($c->scheduled_at) &middot; {{ t('msg.scheduleAt') }}: {{ $c->scheduled_at->format('d-m-Y H:i') }} @endif &middot; {{ t('msg.createdBy') }} {{ $c->created_by }}</div>
    @if(!$windowOpen && in_array($c->status, ['QUEUED', 'SENDING']))<div class="alert alert-info py-2">{{ t('msg.outsideWindow', $sendFrom, $sendUntil) }}</div>@endif
    <div class="row g-2 mb-3">
        @foreach([['msg.p.recipients', $c->total, ''], ['msg.s.SENT', $c->sent, 'text-success'], ['msg.s.FAILED', $c->failed, 'text-danger'], ['msg.s.SKIPPED', $c->skipped, ''], ['msg.s.QUEUED', $c->pending(), ''], ['msg.progress', $c->progress() . '%', '']] as [$l, $n, $cls])
        <div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t($l) }}</div><div class="num {{ $cls }}">{{ $n }}</div></div></div>
        @endforeach
    </div>
    @if($delivery !== null && $c->sent)
    @php($dl = ($delivery['DELIVERED'] ?? 0) + ($delivery['READ'] ?? 0))
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t('dlv.delivered') }}</div><div class="num text-success">{{ $dl }} <span class="small text-muted">({{ round(100 * $dl / max(1, $c->sent)) }}%)</span></div></div></div>
        @if($c->channel === 'WHATSAPP')<div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t('dlv.read') }}</div><div class="num text-primary">{{ $delivery['READ'] ?? 0 }}</div></div></div>@endif
        <div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t('dlv.undelivered') }}</div><div class="num text-danger">{{ $delivery['UNDELIVERED'] ?? 0 }}</div></div></div>
        <div class="col-6 col-md-2"><div class="stat"><div class="small text-muted">{{ t('dlv.noReceipt') }}</div><div class="num">{{ max(0, $c->sent - $dl - ($delivery['UNDELIVERED'] ?? 0)) }}</div></div></div>
    </div>
    @endif
    <div class="card mb-3"><div class="card-body small" dir="auto" style="white-space:pre-wrap">{{ $c->template?->body }}</div></div>
    <div class="d-flex flex-wrap gap-1 mb-2">
        <a class="btn btn-sm {{ $status ? 'btn-outline-secondary' : 'btn-primary' }}" href="{{ url('/messages/campaigns/'.$c->id) }}">{{ t('common.all') }}</a>
        @foreach($statuses as $s)<a class="btn btn-sm {{ $s === $status ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ url('/messages/campaigns/'.$c->id) }}?status={{ $s }}">{{ t('msg.s.'.$s) }}</a>@endforeach
    </div>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-striped mb-0">
        <thead class="table-light"><tr><th>{{ t('col.name') }}</th><th>{{ t('col.mobile') }}</th><th>{{ t('admin.status') }}</th><th>{{ t('msg.attempts') }}</th><th>{{ t('msg.providerId') }}</th><th>{{ t('admin.details') }}</th><th>{{ t('admin.time') }}</th></tr></thead>
        <tbody>@forelse($page as $o)
            <tr><td>@if($o->voter_id)<a href="{{ url('/voters/'.$o->voter_id) }}">{{ $o->voter_name }}</a>@endif</td><td class="text-nowrap">{{ $o->maskedMobile() }}</td>
                <td><span class="badge {{ $o->status === 'SENT' ? 'text-bg-success' : ($o->status === 'FAILED' ? 'text-bg-danger' : 'text-bg-light border') }}">{{ t('msg.s.'.$o->status) }}</span>@if($o->delivery ?? null) <span class="badge {{ $o->delivery === 'UNDELIVERED' ? 'text-bg-danger' : ($o->delivery === 'READ' ? 'text-bg-primary' : 'text-bg-success') }}">{{ t('dlv.' . strtolower($o->delivery)) }}</span>@endif</td>
                <td>{{ $o->attempts }}</td><td class="small">{{ $o->provider_id }}</td><td class="small text-danger">{{ $o->error }}</td><td class="small text-nowrap">{{ $o->sent_at?->format('d-m H:i:s') }}</td></tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-3">{{ t('common.noRecords') }}</td></tr> @endforelse</tbody></table></div>
    <div class="mt-3">{{ $page->links() }}</div>
</div>
@endsection
