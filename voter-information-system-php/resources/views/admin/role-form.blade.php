@extends('layouts.app')
@section('title', t('admin.role'))
@section('content')
<div class="container" style="max-width:950px">
    <h4 class="mb-3">{{ t('admin.role') }}</h4>
    <form method="post" action="{{ url('/admin/roles') }}" class="card" novalidate>@csrf
        <input type="hidden" name="id" value="{{ $item->id }}">
        <div class="card-body row g-3">
            @include('partials.global-errors')
            @include('partials.input', ['name' => 'code', 'label' => 'admin.roleCode', 'cls' => 'col-md-3', 'value' => $item->code, 'readonly' => $item->system_role])
            @include('partials.input', ['name' => 'name', 'label' => 'admin.roleName', 'cls' => 'col-md-4', 'value' => $item->name, 'local' => true])
            @include('partials.input', ['name' => 'description', 'label' => 'admin.roleDescription', 'cls' => 'col-md-5', 'value' => $item->description, 'local' => true])
            <div class="col-12"><label class="form-label fw-semibold">{{ t('admin.permissions') }}</label><div class="row g-3">
                @foreach($groups as $g => $ps)<div class="col-md-4"><div class="border rounded p-2 h-100">
                    <div class="small text-uppercase text-muted mb-1">{{ t('perm.group.'.$g) }}</div>
                    @foreach($ps as $p)<div class="form-check"><input class="form-check-input" type="checkbox" name="perms[]" value="{{ $p }}" id="perm-{{ $p }}" @checked(in_array($p, $perms))>
                        <label class="form-check-label" for="perm-{{ $p }}">{{ t('perm.'.$p) }}<span class="d-block small text-muted">{{ t('perm.'.$p.'.desc') }}</span></label></div>@endforeach
                </div></div>@endforeach
            </div></div>
        </div>
        <div class="card-footer d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ t('common.save') }}</button>
            <a class="btn btn-outline-secondary" href="{{ url('/admin/roles') }}">{{ t('common.cancel') }}</a></div>
    </form>
</div>
@endsection
