@extends('layouts.app')
@section('title', t('admin.audit'))
@section('content')
<div class="container-fluid px-3" style="max-width:1400px">
    <h4 class="mb-3"><i class="bi bi-journal-text"></i> {{ t('admin.audit') }}</h4>
    <form method="get" class="card card-body py-2 mb-3"><div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">{{ t('login.username') }}</label><input name="user" value="{{ $user }}" class="form-control form-control-sm"></div>
        <div class="col-md-3"><label class="form-label small">{{ t('admin.action') }}</label><select name="action" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
            @foreach($actions as $a)<option value="{{ $a }}" @selected($a === $action)>{{ t('audit.'.$a) }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label small">{{ t('admin.entity') }}</label><select name="entity" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
            @foreach($entities as $e)<option value="{{ $e }}" @selected($e === $entity)>{{ t('entity.'.$e) }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ t('common.apply') }}</button></div>
    </div></form>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-striped mb-0">
        <thead class="table-light"><tr><th>{{ t('admin.time') }}</th><th>{{ t('login.username') }}</th><th>{{ t('admin.action') }}</th><th>{{ t('admin.entity') }}</th><th>ID</th><th>{{ t('admin.details') }}</th></tr></thead>
        <tbody>@forelse($page as $a)
            <tr><td class="text-nowrap small">{{ $a->at?->format('d-m-Y H:i:s') }}</td><td>{{ $a->username }}</td>
                <td><span class="badge {{ in_array($a->action, ['DELETE', 'LOGIN_FAILED']) ? 'text-bg-danger' : 'text-bg-light border' }}">{{ t('audit.'.$a->action) }}</span></td>
                <td>{{ $a->entity ? t('entity.'.$a->entity) : '' }}</td><td class="small">{{ $a->entity_id }}</td><td class="small" dir="auto">{{ $a->details }}</td></tr>
        @empty <tr><td colspan="6" class="text-center text-muted py-4">{{ t('common.noRecords') }}</td></tr> @endforelse</tbody></table></div>
    <div class="mt-3">{{ $page->links() }}</div>
</div>
@endsection
