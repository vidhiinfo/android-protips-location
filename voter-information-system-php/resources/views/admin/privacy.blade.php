@extends('layouts.app')
@section('title', t('privacy.title'))
@section('content')
@php($types = \App\Services\Privacy\PrivacyService::TYPES)
<div class="container" style="max-width:1200px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-shield-check"></i> {{ t('privacy.title') }}</h4>
    <p class="small text-muted">{{ t('privacy.intro') }}</p>
    @if($overdue)<div class="alert alert-danger py-2"><i class="bi bi-alarm"></i> {{ t('privacy.overdue', $overdue) }}</div>@endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach(['OPEN', 'DONE', 'REJECTED', 'ALL'] as $st)
                <a class="btn btn-sm {{ $status === $st ? 'btn-dark' : 'btn-outline-secondary' }}" href="?status={{ $st }}">{{ t('privacy.status.' . $st) }}
                    <span class="badge text-bg-light">{{ $st === 'ALL' ? $counts->sum() : ($counts[$st] ?? 0) }}</span></a>
                @endforeach
            </div>
            <div class="table-responsive bg-white border rounded"><table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>#</th><th>{{ t('privacy.type') }}</th><th>{{ t('col.name') }}</th><th>{{ t('privacy.received') }}</th><th>{{ t('privacy.due') }}</th><th>{{ t('roll.status') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($list as $p)
                    @php($late = $p->status === 'OPEN' && $p->due_at && \Illuminate\Support\Carbon::parse($p->due_at)->isPast())
                    <tr @class(['table-danger' => $late])><td>{{ $p->id }}</td><td><span class="badge text-bg-primary">{{ t('privacy.type.' . $p->type) }}</span></td>
                        <td>@if($p->voter_id)<a href="{{ url('/voters/' . $p->voter_id) }}">{{ $p->name }}</a>@else{{ $p->name }}@endif<div class="small text-muted">{{ $p->epic_no }} @if($p->channel)· {{ $p->channel }}@endif</div>
                            @if($p->details)<div class="small">{{ $p->details }}</div>@endif</td>
                        <td class="small">{{ \Illuminate\Support\Carbon::parse($p->requested_at)->format('d-m-Y') }}<div class="text-muted">{{ $p->created_by }}</div></td>
                        <td class="small {{ $late ? 'text-danger fw-semibold' : '' }}">{{ $p->due_at ? \Illuminate\Support\Carbon::parse($p->due_at)->format('d-m-Y') : '' }}</td>
                        <td>@if($p->status === 'OPEN')<span class="badge text-bg-warning">{{ t('privacy.status.OPEN') }}</span>@else<span class="badge {{ $p->status === 'DONE' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ t('privacy.status.' . $p->status) }}</span>
                            <div class="small text-muted">{{ $p->done_by }} · {{ \Illuminate\Support\Carbon::parse($p->done_at)->format('d-m') }}</div>@if($p->outcome)<div class="small">{{ $p->outcome }}</div>@endif @endif</td>
                        <td>@if($p->status === 'OPEN')
                            <form method="post" action="{{ url('/admin/privacy/' . $p->id . '/close') }}" class="d-flex gap-1">@csrf
                                <input name="outcome" class="form-control form-control-sm" placeholder="{{ t('privacy.outcome') }}" style="min-width:9rem">
                                <button name="status" value="DONE" class="btn btn-sm btn-success" title="{{ t('privacy.status.DONE') }}"><i class="bi bi-check2"></i></button>
                                <button name="status" value="REJECTED" class="btn btn-sm btn-outline-secondary" title="{{ t('privacy.status.REJECTED') }}"><i class="bi bi-x"></i></button></form>@endif</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">{{ t('privacy.none') }}</td></tr>
                @endforelse
                </tbody></table></div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body">
                <h6>{{ t('privacy.new') }}</h6>
                <form method="post" action="{{ url('/admin/privacy') }}">@csrf
                    <select name="type" class="form-select form-select-sm mb-2">@foreach($types as $ty)<option value="{{ $ty }}">{{ t('privacy.type.' . $ty) }}</option>@endforeach</select>
                    <input name="epic_no" class="form-control form-control-sm mb-2" placeholder="{{ t('col.epicNo') }}">
                    <input name="name" class="form-control form-control-sm mb-2" placeholder="{{ t('privacy.nameHelp') }}">
                    <input name="channel" class="form-control form-control-sm mb-2" placeholder="{{ t('privacy.channel') }}">
                    <textarea name="details" class="form-control form-control-sm mb-2" rows="2" placeholder="{{ t('privacy.details') }}"></textarea>
                    <button class="btn btn-sm btn-primary w-100">{{ t('common.save') }}</button>
                </form>
                <div class="small text-muted mt-2">{{ t('privacy.howTo') }}</div>
            </div></div>
            <div class="card border-danger"><div class="card-body">
                <h6 class="text-danger">{{ t('privacy.retention') }}</h6>
                <p class="small">{{ t('privacy.retentionHelp') }}</p>
                <form method="post" action="{{ url('/admin/privacy/retention') }}" data-confirm="{{ t('privacy.retentionConfirm') }}">@csrf
                    <select name="assembly_no" class="form-select form-select-sm mb-2" required><option value="">--</option>
                        @foreach($assemblies as $no => $nm)<option value="{{ $no }}">{{ $no }} - {{ $nm }}</option>@endforeach</select>
                    <input name="confirm" class="form-control form-control-sm mb-2" inputmode="numeric" placeholder="{{ t('privacy.typeAssembly') }}" required>
                    <button class="btn btn-sm btn-danger w-100">{{ t('privacy.retentionBtn') }}</button>
                </form>
                <div class="small text-muted mt-2">{{ t('privacy.messagesKept', (int) config('voters.privacy.message_retention_days', 365)) }}</div>
            </div></div>
        </div>
    </div>
</div>
@endsection
