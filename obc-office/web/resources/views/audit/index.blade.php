@extends('layouts.app', ['pageTitle' => 'Audit Log'])

@section('content')
<form class="mb-4 flex flex-wrap items-end gap-2">
    <div><label class="label">Action</label><select name="action" class="input" data-autosubmit><option value="">All</option>@foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select></div>
    <div><label class="label">Record type</label><select name="entity" class="input" data-autosubmit><option value="">All</option>@foreach ($entities as $e)<option @selected(request('entity') === $e)>{{ $e }}</option>@endforeach</select></div>
</form>
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Detail</th><th>IP</th></tr></thead>
        <tbody>
        @foreach ($logs as $l)
            <tr>
                <td class="text-xs whitespace-nowrap">{{ fmt_datetime($l->created_at) }}</td>
                <td class="text-sm">{{ $l->user?->name ?? '—' }}</td>
                <td><span class="{{ in_array($l->action, ['delete', 'export', 'reject', 'disable']) ? 'badge-amber' : 'badge-gray' }}">{{ $l->action }}</span></td>
                <td class="text-xs">{{ $l->entity }} {{ $l->entity_id ? '#'.$l->entity_id : '' }}</td>
                <td class="max-w-md truncate text-xs text-slate-600">{{ $l->detail }}</td>
                <td class="font-mono text-xs text-slate-400">{{ $l->ip }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
