@extends('layouts.app')
@section('title', t('msg.campaigns'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('messages._banners')
    <div class="d-flex align-items-center mb-3"><h4 class="me-auto mb-0"><i class="bi bi-chat-dots"></i> {{ t('msg.campaigns') }}</h4>
        <a class="btn btn-sm btn-primary" href="{{ url('/voters') }}"><i class="bi bi-plus-lg"></i> {{ t('msg.new') }}</a></div>
    <p class="small text-muted">{{ t('msg.howTo') }}</p>
    <div class="table-responsive bg-white border rounded"><table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light"><tr><th>#</th><th>{{ t('msg.campaignName') }}</th><th>{{ t('msg.channel') }}</th><th>{{ t('msg.template') }}</th><th>{{ t('admin.status') }}</th>
            <th style="width:18%">{{ t('msg.progress') }}</th><th class="text-end">{{ t('msg.s.SENT') }}</th><th class="text-end">{{ t('msg.s.FAILED') }}</th><th>{{ t('msg.createdBy') }}</th><th>{{ t('admin.time') }}</th></tr></thead>
        <tbody>@forelse($page as $c)
            <tr><td>{{ $c->id }}</td><td><a href="{{ url('/messages/campaigns/'.$c->id) }}" dir="auto">{{ $c->name }}</a></td>
                <td><i class="bi {{ $c->channel === 'SMS' ? 'bi-chat-text' : 'bi-whatsapp text-success' }}"></i> {{ t('msg.channel.'.$c->channel) }}</td>
                <td class="small">{{ $c->template?->name }}</td><td><span class="badge text-bg-light border">{{ t('msg.c.'.$c->status) }}</span></td>
                <td><div class="progress" style="height:.6rem"><div class="progress-bar" style="width:{{ $c->progress() }}%"></div></div><span class="small text-muted">{{ $c->total - $c->pending() }} / {{ $c->total }}</span></td>
                <td class="text-end">{{ $c->sent }}</td><td class="text-end">{{ $c->failed }}</td><td>{{ $c->created_by }}</td><td class="small">{{ $c->created_at?->format('d-m-Y H:i') }}</td></tr>
        @empty <tr><td colspan="10" class="text-center text-muted py-4">{{ t('common.noRecords') }}</td></tr> @endforelse</tbody></table></div>
    <div class="mt-3">{{ $page->links() }}</div>
</div>
@endsection
