@extends('layouts.app')
@section('title', t('admin.users'))
@section('content')
<div class="container-fluid px-3" style="max-width:1200px">
    @include('partials.flash')
    <div class="d-flex align-items-center mb-3"><h4 class="me-auto mb-0"><i class="bi bi-people"></i> {{ t('admin.users') }}</h4>
        <a class="btn btn-outline-secondary btn-sm me-2" href="{{ url('/admin/roles') }}"><i class="bi bi-shield-check"></i> {{ t('admin.roles') }}</a>
        @unless($externalUsers)<a class="btn btn-primary btn-sm" href="{{ url('/admin/users/new') }}"><i class="bi bi-plus-lg"></i> {{ t('common.add') }}</a>@endunless</div>
    @if($externalUsers)<div class="alert alert-info py-2">{{ t('admin.externalUsers') }}</div>
    @else
    <div class="table-responsive bg-white border rounded"><table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light"><tr><th>{{ t('login.username') }}</th><th>{{ t('admin.fullName') }}</th><th>{{ t('admin.roles') }}</th><th>{{ t('login.assembly') }}</th><th>{{ t('area.title') }}</th><th>{{ t('otp.column') }}</th><th>{{ t('admin.status') }}</th><th>{{ t('admin.lastLogin') }}</th><th></th></tr></thead>
        <tbody>@foreach($items as $i)
            <tr><td class="fw-semibold">{{ $i->username }}</td><td>{{ $i->full_name }}</td>
                <td>@foreach($i->roles as $r)<span class="badge text-bg-secondary me-1" title="{{ $r->code }}">{{ $r->name }}</span>@endforeach</td>
                <td>{{ $i->assembly_no ?? t('login.allAssemblies') }}</td>
                <td class="small">{{ \App\Support\Area::label(\App\Support\Area::of($i)) ?: '-' }}</td>
                <td class="small text-nowrap">@if($i->otp_method)<span class="badge text-bg-success">{{ t('otp.method.' . $i->otp_method) }}</span>
                    <form action="{{ url('/admin/users/'.$i->id.'/otp-reset') }}" method="post" class="d-inline" data-confirm="{{ t('otp.resetConfirm') }}">@csrf<button class="btn btn-link btn-sm p-0 ms-1">{{ t('otp.reset') }}</button></form>@else<span class="text-muted">-</span>@endif</td>
                <td><span class="badge {{ $i->enabled ? 'text-bg-success' : 'text-bg-danger' }}">{{ $i->enabled ? t('admin.active') : t('admin.disabled') }}</span></td>
                <td class="small">{{ $i->last_login?->format('d-m-Y H:i') }}</td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ url('/admin/users/'.$i->id.'/edit') }}"><i class="bi bi-pencil"></i></a>
                    <form action="{{ url('/admin/users/'.$i->id.'/delete') }}" method="post" class="d-inline" data-confirm="{{ t('common.confirmDelete') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        @endforeach</tbody></table></div>
    @endif
</div>
@endsection
