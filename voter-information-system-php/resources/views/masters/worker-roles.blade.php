@extends('layouts.app')
@section('title', t('wrole.title'))
@section('content')
@php($edit = can('MASTER_EDIT'))
<div class="container-fluid px-3" style="max-width:1200px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-person-badge"></i> {{ t('wrole.title') }}</h4>
    <p class="small text-muted mb-3">{{ t('wrole.intro') }}</p>
    <form method="post" action="{{ url('/masters/worker-roles') }}">@csrf
    <div class="table-responsive bg-white border rounded"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr>
            <th style="width:5rem">{{ t('wrole.order') }}</th><th>{{ t('wrole.nameEn') }}</th><th>{{ t('wrole.nameHi') }}</th><th>{{ t('wrole.namePa') }}</th>
            <th>{{ t('wrole.worksLike') }}</th><th class="text-center">{{ t('wrole.show') }}</th><th class="text-end">{{ t('ls.workers') }}</th>@if($edit)<th></th>@endif
        </tr></thead>
        <tbody>@foreach($roles as $code => $r)
            <tr class="{{ $r['active'] ? '' : 'table-secondary' }}">
                <td><input type="number" name="r[{{ $code }}][sort]" value="{{ $r['sort'] }}" min="0" max="9999" class="form-control form-control-sm" @disabled(!$edit)></td>
                <td><input name="r[{{ $code }}][name]" value="{{ $r['name'] }}" maxlength="60" class="form-control form-control-sm" placeholder="{{ $r['builtin'] ? \App\Support\Lang::get('worker.role.' . $code, [], 'en') : '' }}" @disabled(!$edit) @required(!$r['builtin'])>
                    @if($r['builtin'])<div class="form-text mt-0"><span class="badge text-bg-light border">{{ t('wrole.builtin') }}</span></div>@endif</td>
                <td><input name="r[{{ $code }}][name_hi]" value="{{ $r['name_hi'] }}" maxlength="60" class="form-control form-control-sm" lang="hi" placeholder="{{ $r['builtin'] ? \App\Support\Lang::get('worker.role.' . $code, [], 'hi') : '' }}" @disabled(!$edit)></td>
                <td><input name="r[{{ $code }}][name_pa]" value="{{ $r['name_pa'] }}" maxlength="60" class="form-control form-control-sm" lang="pa" placeholder="{{ $r['builtin'] ? \App\Support\Lang::get('worker.role.' . $code, [], 'pa') : '' }}" @disabled(!$edit)></td>
                <td>@if($r['builtin'])<span class="small text-muted">{{ t('wrole.itself') }}</span>
                    @else<select name="r[{{ $code }}][base]" class="form-select form-select-sm" @disabled(!$edit)>
                        @foreach($bases as $b)<option value="{{ $b }}" @selected($b === $r['base'])>{{ role_label($b) }}</option>@endforeach</select>@endif</td>
                <td class="text-center"><input type="checkbox" class="form-check-input" name="r[{{ $code }}][active]" value="1" @checked($r['active']) @disabled(!$edit)></td>
                <td class="text-end">{{ $counts[$code] ?? 0 }}</td>
                @if($edit)<td class="text-end">@unless($r['builtin'])<button class="btn btn-sm btn-outline-danger" formaction="{{ url('/masters/worker-roles/' . $code . '/delete') }}" data-confirm="{{ t('common.confirmDelete') }}" title="{{ t('common.delete') }}" @disabled(($counts[$code] ?? 0) > 0)><i class="bi bi-trash"></i></button>@endunless</td>@endif
            </tr>
        @endforeach</tbody>
    </table></div>
    @if($edit)<button class="btn btn-primary mt-2"><i class="bi bi-check2"></i> {{ t('common.save') }}</button>@endif
    </form>
    <div class="small text-muted mt-2">{{ t('wrole.help') }}</div>

    @if($edit)
    <div class="card mt-3"><div class="card-header fw-semibold"><i class="bi bi-plus-lg"></i> {{ t('wrole.add') }}</div><div class="card-body">
        <form method="post" action="{{ url('/masters/worker-roles/add') }}" class="row g-2 align-items-end">@csrf
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('wrole.nameEn') }} *</label><input name="name" class="form-control form-control-sm" required maxlength="60" placeholder="Shakti Kendra Pramukh"></div>
            <div class="col-md-2"><label class="form-label small mb-0">{{ t('wrole.nameHi') }}</label><input name="name_hi" class="form-control form-control-sm" maxlength="60" lang="hi"></div>
            <div class="col-md-2"><label class="form-label small mb-0">{{ t('wrole.namePa') }}</label><input name="name_pa" class="form-control form-control-sm" maxlength="60" lang="pa"></div>
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('wrole.worksLike') }} *</label><select name="base" class="form-select form-select-sm">
                @foreach($bases as $b)<option value="{{ $b }}" @selected($b === 'VOLUNTEER')>{{ role_label($b) }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ t('common.add') }}</button></div>
            <div class="col-12 form-text">{{ t('wrole.worksLikeHelp') }}</div>
        </form>
    </div></div>
    @endif
</div>
@endsection
