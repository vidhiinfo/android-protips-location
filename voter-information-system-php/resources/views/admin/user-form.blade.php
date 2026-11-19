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
                <select name="assembly_no" class="form-select" data-area-assembly><option value="">{{ t('login.allAssemblies') }}</option>
                    @foreach($assemblyList as $a)<option value="{{ $a->assembly_no }}" @selected($a->assembly_no == $item->assembly_no)>{{ $a->assembly_no }} - {{ $a->name }}</option>@endforeach</select>
                <div class="form-text">{{ t('admin.assignedAssemblyHelp') }}</div></div>
            <div class="col-12"><div class="border rounded p-3 bg-light">
                <div class="fw-semibold mb-1"><i class="bi bi-bounding-box"></i> {{ t('area.title') }}</div>
                <div class="small text-muted mb-2">{{ t('area.help') }}</div>
                <div class="row g-2" data-area-picker data-map='@json((object) $areaMap)' data-all="{{ t('common.all') }}" data-none="{{ t('area.noData') }}"
                     data-selected-n="{{ t('area.selectedN') }}" data-page="{{ t('area.page') }}">
                    @php($ae = fn ($f) => isset($errors) ? $errors->first($f) : null)
                    @foreach(\App\Models\Voter::showWard() ? ['scope_wards' => 'area.wards', 'scope_booths' => 'area.booths'] : ['scope_booths' => 'area.booths'] as $fld => $lbl)
                    <div class="col-md-{{ $fld === 'scope_wards' ? 4 : 8 }}"><label class="form-label small mb-0">{{ t($lbl) }}</label>
                        <input type="hidden" name="{{ $fld }}" value="{{ old($fld, $item->$fld) }}" data-area-value="{{ $fld }}">
                        <div class="dropdown" data-area-dd="{{ $fld }}">
                            <button type="button" class="form-select text-start {{ $ae($fld) ? 'is-invalid' : '' }}" data-bs-toggle="dropdown" data-bs-auto-close="outside">{{ t('common.all') }}</button>
                            <div class="dropdown-menu p-2 w-100" style="max-height:280px;overflow:auto"></div>
                        </div>
                        @if($ae($fld))<div class="invalid-feedback d-block">{{ $ae($fld) }}</div>@endif</div>
                    @endforeach
                    <div class="col-md-8"><label class="form-label small mb-0">{{ t('area.serialRange') }}</label>
                        <div class="input-group">
                            <select class="form-select" data-area-page style="max-width:13rem" title="{{ t('area.pageHelp') }}"><option value="">{{ t('area.wholeBooth') }}</option></select>
                            <input name="scope_serial_from" type="number" min="1" value="{{ old('scope_serial_from', $item->scope_serial_from) }}" class="form-control {{ $ae('scope_serial_from') ? 'is-invalid' : '' }}" placeholder="{{ t('area.serialFrom') }}" aria-label="{{ t('area.serialFrom') }}">
                            <input name="scope_serial_to" type="number" min="1" value="{{ old('scope_serial_to', $item->scope_serial_to) }}" class="form-control" placeholder="{{ t('area.serialTo') }}" aria-label="{{ t('area.serialTo') }}">
                        </div>
                        @if($ae('scope_serial_from'))<div class="invalid-feedback d-block">{{ $ae('scope_serial_from') }}</div>@endif
                        <div class="form-text">{{ t('area.pageHelp') }}</div></div>
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
