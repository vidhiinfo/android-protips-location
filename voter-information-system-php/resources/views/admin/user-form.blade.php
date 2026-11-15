@extends('layouts.app')
@section('title', t('admin.user'))
@section('content')
<div class="container" style="max-width:850px">
    <h4 class="mb-3">{{ t('admin.user') }}</h4>
    <form method="post" action="{{ url('/admin/users') }}" class="card" novalidate>@csrf
        <input type="hidden" name="id" value="{{ $item->id }}">
        <div class="card-body row g-3">
            @include('partials.global-errors')
            @include('partials.input', ['name' => 'username', 'label' => 'login.username', 'cls' => 'col-md-6', 'value' => $item->username, 'readonly' => $item->exists])
            @include('partials.input', ['name' => 'full_name', 'label' => 'admin.fullName', 'cls' => 'col-md-6', 'value' => $item->full_name, 'local' => true])
            <div class="col-12"><label class="form-label">{{ t('admin.roles') }}</label><div class="row g-2">
                @foreach($roleList as $r)<div class="col-md-6"><label class="border rounded p-2 d-flex gap-2 h-100">
                    <input type="checkbox" class="form-check-input mt-1" name="roleIds[]" value="{{ $r->id }}" @checked(in_array($r->id, $roleIds))>
                    <span><b>{{ $r->name }}</b> <code class="small">{{ $r->code }}</code><span class="d-block small text-muted">{{ $r->description }}</span></span></label></div>@endforeach
            </div><div class="form-text">{{ t('admin.rolesHelp') }}</div></div>
            <div class="col-md-6"><label class="form-label">{{ t('admin.assignedAssembly') }}</label>
                <select name="assembly_no" class="form-select"><option value="">{{ t('login.allAssemblies') }}</option>
                    @foreach($assemblyList as $a)<option value="{{ $a->assembly_no }}" @selected($a->assembly_no == $item->assembly_no)>{{ $a->assembly_no }} - {{ $a->name }}</option>@endforeach</select>
                <div class="form-text">{{ t('admin.assignedAssemblyHelp') }}</div></div>
            <div class="col-md-6 d-flex align-items-center"><div class="form-check mt-3"><input type="checkbox" class="form-check-input" id="enabled" name="enabled" value="1" @checked($item->enabled)>
                <label class="form-check-label" for="enabled">{{ t('admin.active') }}</label></div></div>
            <div class="col-md-6"><label class="form-label" for="newPassword">{{ $item->exists ? t('admin.resetPassword') : t('admin.password') }}</label>
                <input id="newPassword" name="newPassword" type="password" class="form-control" autocomplete="new-password" minlength="8"><div class="form-text">{{ t('validation.password') }}</div></div>
        </div>
        <div class="card-footer d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ t('common.save') }}</button>
            <a class="btn btn-outline-secondary" href="{{ url('/admin/users') }}">{{ t('common.cancel') }}</a></div>
    </form>
</div>
@endsection
