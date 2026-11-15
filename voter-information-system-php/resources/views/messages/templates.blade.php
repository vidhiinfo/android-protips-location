@extends('layouts.app')
@section('title', t('msg.templates'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('messages._banners')
    <div class="d-flex align-items-center mb-3"><h4 class="me-auto mb-0"><i class="bi bi-card-text"></i> {{ t('msg.templates') }}</h4>
        <a class="btn btn-sm btn-primary" href="{{ url('/messages/templates/new') }}"><i class="bi bi-plus-lg"></i> {{ t('common.add') }}</a></div>
    <div class="table-responsive bg-white border rounded"><table class="table table-striped mb-0 align-middle">
        <thead class="table-light"><tr><th>{{ t('msg.channel') }}</th><th>{{ t('nav.language') }}</th><th>{{ t('msg.templateName') }}</th><th>{{ t('msg.body') }}</th><th>{{ t('msg.providerTemplateId') }}</th><th>{{ t('admin.status') }}</th><th></th></tr></thead>
        <tbody>@forelse($items as $i)
            <tr><td>{{ t('msg.channel.'.$i->channel) }}</td><td>{{ $i->language }}</td><td class="fw-semibold">{{ $i->name }}</td><td class="small" dir="auto" style="max-width:420px">{{ $i->body }}</td>
                <td class="small">{{ $i->provider_template_id }}</td><td><span class="badge {{ $i->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $i->active ? t('admin.active') : t('admin.disabled') }}</span></td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ url('/messages/templates/'.$i->id.'/edit') }}"><i class="bi bi-pencil"></i></a>
                    <form action="{{ url('/messages/templates/'.$i->id.'/delete') }}" method="post" class="d-inline" data-confirm="{{ t('common.confirmDelete') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-4">{{ t('common.noRecords') }}</td></tr> @endforelse</tbody></table></div>
</div>
@endsection
