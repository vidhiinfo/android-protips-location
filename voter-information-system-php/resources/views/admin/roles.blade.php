@extends('layouts.app')
@section('title', t('admin.roles'))
@section('content')
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex align-items-center mb-2"><h4 class="me-auto mb-0"><i class="bi bi-shield-check"></i> {{ t('admin.roles') }}</h4>
        <a class="btn btn-primary btn-sm" href="{{ url('/admin/roles/new') }}"><i class="bi bi-plus-lg"></i> {{ t('common.add') }}</a></div>
    <p class="small text-muted">{{ t('admin.rolesIntro') }}</p>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-bordered mb-0 align-middle text-center">
        <thead class="table-light"><tr><th class="text-start">{{ t('admin.role') }}</th><th>{{ t('admin.users') }}</th>
            @foreach($permissions as $p)<th class="small" style="writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap">{{ t('perm.'.$p) }}</th>@endforeach<th></th></tr></thead>
        <tbody>@foreach($items as $r) @php($rp = $r->permissions())
            <tr><td class="text-start"><b>{{ $r->name }}</b> <code class="small">{{ $r->code }}</code> @if($r->system_role)<i class="bi bi-lock-fill text-muted small" title="{{ t('admin.systemRole') }}"></i>@endif
                <div class="small text-muted">{{ $r->description }}</div></td>
                <td>{{ $r->userCount() }}</td>
                @foreach($permissions as $p)<td>@if(in_array($p, $rp))<i class="bi bi-check-lg text-success" title="{{ t('perm.'.$p) }}"></i>@endif</td>@endforeach
                <td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ url('/admin/roles/'.$r->id.'/edit') }}"><i class="bi bi-pencil"></i></a>
                    @unless($r->system_role)<form action="{{ url('/admin/roles/'.$r->id.'/delete') }}" method="post" class="d-inline" data-confirm="{{ t('common.confirmDelete') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endunless</td></tr>
        @endforeach</tbody></table></div>
</div>
@endsection
