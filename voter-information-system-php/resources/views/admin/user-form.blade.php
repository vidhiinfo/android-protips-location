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
            <div class="col-12"><div class="border rounded p-3 bg-light">
                <div class="fw-semibold mb-1"><i class="bi bi-bounding-box"></i> {{ t('area.title') }}</div>
                <div class="small text-muted mb-2">{{ t('area.help') }}</div>
                <div class="row g-2">
                    @php($ae = fn ($f) => isset($errors) ? $errors->first($f) : null)
                    <div class="col-md-3"><label class="form-label small mb-0" for="scope_wards">{{ t('area.wards') }}</label>
                        <input id="scope_wards" name="scope_wards" value="{{ old('scope_wards', $item->scope_wards) }}" class="form-control {{ $ae('scope_wards') ? 'is-invalid' : '' }}" placeholder="101, 102" inputmode="numeric">
                        @if($ae('scope_wards'))<div class="invalid-feedback">{{ $ae('scope_wards') }}</div>@endif</div>
                    <div class="col-md-5"><label class="form-label small mb-0" for="scope_booths">{{ t('area.booths') }}</label>
                        <input id="scope_booths" name="scope_booths" value="{{ old('scope_booths', $item->scope_booths) }}" class="form-control {{ $ae('scope_booths') ? 'is-invalid' : '' }}" placeholder="3, 4, 5" inputmode="numeric">
                        @if($ae('scope_booths'))<div class="invalid-feedback">{{ $ae('scope_booths') }}</div>@endif</div>
                    <div class="col-6 col-md-2"><label class="form-label small mb-0" for="scope_serial_from">{{ t('area.serialFrom') }}</label>
                        <input id="scope_serial_from" name="scope_serial_from" type="number" min="1" value="{{ old('scope_serial_from', $item->scope_serial_from) }}" class="form-control {{ $ae('scope_serial_from') ? 'is-invalid' : '' }}">
                        @if($ae('scope_serial_from'))<div class="invalid-feedback">{{ $ae('scope_serial_from') }}</div>@endif</div>
                    <div class="col-6 col-md-2"><label class="form-label small mb-0" for="scope_serial_to">{{ t('area.serialTo') }}</label>
                        <input id="scope_serial_to" name="scope_serial_to" type="number" min="1" value="{{ old('scope_serial_to', $item->scope_serial_to) }}" class="form-control {{ $ae('scope_serial_to') ? 'is-invalid' : '' }}"></div>
                </div>
                <div class="small text-muted mt-2">{{ t('area.examples') }}</div>
            </div></div>
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
