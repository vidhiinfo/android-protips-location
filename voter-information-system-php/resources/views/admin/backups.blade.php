@extends('layouts.app')
@section('title', t('backup.title'))
@section('content')
<div class="container" style="max-width:1000px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-database-check"></i> {{ t('backup.title') }}</h4>
            <div class="small text-muted">{{ t('backup.intro', $keep) }}</div></div>
        <form method="post" action="{{ url('/admin/backups') }}">@csrf
            <button class="btn btn-primary btn-sm" data-busy="{{ t('backup.running') }}"><i class="bi bi-database-down"></i> {{ t('backup.now') }}</button></form>
    </div>
    @if($due)<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> {{ t('backup.dueWarn') }}</div>@endif
    <div class="table-responsive bg-white border rounded mb-3"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>{{ t('backup.file') }}</th><th>{{ t('backup.when') }}</th><th class="text-end">{{ t('backup.size') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($list as $b)
            <tr><td><code>{{ $b['name'] }}</code></td><td>{{ date('d-m-Y H:i', $b['time']) }}</td>
                <td class="text-end">{{ $b['size'] >= 1048576 ? number_format($b['size'] / 1048576, 1) . ' MB' : number_format($b['size'] / 1024) . ' KB' }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ url('/admin/backups/' . $b['name']) }}"><i class="bi bi-download"></i> {{ t('backup.download') }}</a></td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-3">{{ t('backup.none') }}</td></tr>
        @endforelse
        </tbody></table></div>
    <div class="card"><div class="card-body small">
        <h6>{{ t('backup.how') }}</h6>
        <ol class="mb-2">
            <li>{{ t('backup.cron1') }}<br><code class="user-select-all">{{ $cron }}</code></li>
            <li>{{ $onVisit ? t('backup.visitOn') : t('backup.visitOff') }}</li>
            <li>{{ t('backup.offsite') }}</li>
            <li>{{ t('backup.restore') }}</li>
        </ol>
        <div class="text-danger"><i class="bi bi-shield-lock"></i> {{ t('backup.private') }}</div>
    </div></div>
</div>
@endsection
