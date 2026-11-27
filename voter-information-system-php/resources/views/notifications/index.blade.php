@extends('layouts.app')
@section('title', t('notif.title'))
@section('content')
<div class="container" style="max-width:860px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h4 class="me-auto mb-0"><i class="bi bi-bell"></i> {{ t('notif.title') }}</h4>
        <form method="post" action="{{ url('/notifications/read-all') }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all"></i> {{ t('notif.markAll') }}</button></form>
        @if(can('WORKER_MANAGE'))<a class="btn btn-sm btn-primary" href="{{ url('/notifications/send') }}"><i class="bi bi-megaphone"></i> {{ t('notif.send') }}</a>@endif
    </div>
    <div class="list-group">
        @forelse($page as $nt)
        <a class="list-group-item list-group-item-action @if(!$nt->read_at) list-group-item-primary @endif" href="{{ url('/notifications/' . $nt->id) }}">
            <div class="d-flex gap-2"><div class="fw-semibold me-auto">{{ $nt->title }}</div><span class="badge text-bg-light border">{{ t('notif.kind.' . $nt->kind) }}</span></div>
            @if($nt->body)<div class="small">{{ $nt->body }}</div>@endif
            <div class="small text-muted">{{ \Carbon\Carbon::parse($nt->created_at)->format('d-m-Y H:i') }}@if($nt->created_by && $nt->created_by !== 'system') · {{ $nt->created_by }}@endif</div>
        </a>
        @empty<div class="text-muted">{{ t('notif.none') }}</div>@endforelse
    </div>
    <div class="mt-3">{{ $page->links() }}</div>
</div>
@endsection
