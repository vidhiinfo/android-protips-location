@extends('layouts.app')
@section('title', t('msg.optouts'))
@section('content')
<div class="container" style="max-width:1000px">
    @include('messages._banners')
    <h4 class="mb-2"><i class="bi bi-bell-slash"></i> {{ t('msg.optouts') }}</h4>
    <p class="small text-muted">{{ t('msg.optoutHelp') }}</p>
    <form method="post" action="{{ url('/messages/optouts') }}" class="card card-body mb-3">@csrf<div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">{{ t('col.mobile') }}</label><input name="mobile" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label small">{{ t('msg.channel') }}</label><select name="channel" class="form-select"><option value="">{{ t('msg.allChannels') }}</option>
            @foreach($channels as $c)<option value="{{ $c }}">{{ t('msg.channel.'.$c) }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label small">{{ t('msg.reason') }}</label><input name="reason" class="form-control" maxlength="200" dir="auto"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">{{ t('common.add') }}</button></div>
    </div></form>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-striped mb-0">
        <thead class="table-light"><tr><th>{{ t('col.mobile') }}</th><th>{{ t('msg.channel') }}</th><th>{{ t('msg.reason') }}</th><th>{{ t('msg.createdBy') }}</th><th>{{ t('admin.time') }}</th><th></th></tr></thead>
        <tbody>@forelse($items as $o)
            <tr><td>{{ $o->mobile }}</td><td>{{ $o->channel ? t('msg.channel.'.$o->channel) : t('msg.allChannels') }}</td><td dir="auto">{{ $o->reason }}</td><td>{{ $o->created_by }}</td>
                <td class="small">{{ $o->created_at?->format('d-m-Y H:i') }}</td>
                <td class="text-end"><form action="{{ url('/messages/optouts/'.$o->id.'/delete') }}" method="post" data-confirm="{{ t('common.confirmDelete') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @empty <tr><td colspan="6" class="text-center text-muted py-3">{{ t('common.noRecords') }}</td></tr> @endforelse</tbody></table></div>
</div>
@endsection
