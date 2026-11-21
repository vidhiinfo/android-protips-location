@extends('layouts.app')
@section('title', t('bulkUndo.title'))
@section('content')
<div class="container" style="max-width:1100px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-clock-history"></i> {{ t('bulkUndo.title') }}</h4>
    <p class="small text-muted">{{ t('bulkUndo.intro') }}</p>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>{{ t('bulkUndo.when') }}</th><th>{{ t('bulkUndo.by') }}</th><th>{{ t('bulkUndo.what') }}</th><th class="text-end">{{ t('bulkUndo.voters') }}</th><th>{{ t('roll.status') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($list as $c)
            @php($fields = json_decode($c->fields, true) ?: [])
            <tr><td>{{ $c->id }}</td><td class="small text-nowrap">{{ \Illuminate\Support\Carbon::parse($c->created_at)->format('d-m-Y H:i') }}</td><td>{{ $c->created_by }}</td>
                <td class="small">@foreach($fields as $f => $val)<span class="badge text-bg-light border me-1">{{ $f === 'worker_id' ? t('worker.title') : t('field.' . \Illuminate\Support\Str::camel($f)) }}: {{ $val ?? t('bulkUndo.cleared') }}</span>@endforeach</td>
                <td class="text-end">{{ number_format($c->voter_count) }}</td>
                <td>@if($c->status === 'APPLIED')<span class="badge text-bg-success">{{ t('roll.status.APPLIED') }}</span>
                    @else<span class="badge text-bg-secondary">{{ t('roll.status.UNDONE') }}</span><div class="small text-muted">{{ $c->undone_by }} · {{ \Illuminate\Support\Carbon::parse($c->undone_at)->format('d-m H:i') }}@if($c->skipped) · {{ t('bulkUndo.kept', $c->skipped) }}@endif</div>@endif</td>
                <td class="text-end">@if($c->status === 'APPLIED')<form method="post" action="{{ url('/voters/bulk-changes/' . $c->id . '/undo') }}" data-confirm="{{ t('bulkUndo.confirm', $c->voter_count) }}">@csrf
                    <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i> {{ t('roll.undo') }}</button></form>@endif</td></tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-3">{{ t('bulkUndo.none') }}</td></tr>
        @endforelse
        </tbody></table></div>
</div>
@endsection
