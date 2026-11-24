@extends('layouts.app', ['pageTitle' => 'Petitions & Grievances'])

@section('actions')
    @if (auth()->user()->canManage('petitions'))<a href="{{ route('petitions.create') }}" class="btn-primary">+ Register petition</a>@endif
@endsection

@section('content')
<form class="no-print mb-4 flex flex-wrap items-end gap-2">
    <div class="min-w-48 flex-1"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input" placeholder="Tracking no., name, mobile, subject"></div>
    @foreach (['status' => $statuses, 'category' => $categories, 'priority' => $priorities, 'state_id' => $states] as $f => $opts)
        <div>
            <label class="label">{{ ucfirst(str_replace('_id', '', $f)) }}</label>
            <select name="{{ $f }}" class="input" data-autosubmit><option value="">All</option>
                @foreach ($opts as $k => $l)<option value="{{ $k }}" @selected((string) request($f) === (string) $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
    @endforeach
    <label class="flex items-center gap-1.5 pb-2 text-sm"><input type="checkbox" name="overdue" value="1" @checked(request('overdue')) data-autosubmit> Overdue only</label>
    <button class="btn-secondary">Filter</button>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Tracking no.</th><th>Petitioner</th><th>Subject</th><th>Category</th><th>Received</th><th>Due</th><th>Priority</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($petitions as $p)
            @php $overdue = $p->due_date && $p->due_date->isPast() && $p->status !== setting('petition_closed_status'); @endphp
            <tr>
                <td><a href="{{ route('petitions.show', $p) }}" class="font-mono text-xs font-semibold text-brand hover:underline">{{ $p->tracking_no }}</a></td>
                <td>{{ $p->petitioner_name }}<div class="text-xs text-slate-500">{{ collect([$p->district?->name, $p->state?->name])->filter()->implode(', ') }}</div></td>
                <td class="max-w-xs truncate">{{ $p->subject }}</td>
                <td class="text-xs">{{ lookup_label('petition_category', $p->category) }}</td>
                <td class="text-xs whitespace-nowrap">{{ fmt_date($p->received_on) }}</td>
                <td class="text-xs whitespace-nowrap {{ $overdue ? 'font-semibold text-red-600' : '' }}">{{ fmt_date($p->due_date) }}</td>
                <td>@include('partials.badge', ['group' => 'priority', 'value' => $p->priority])</td>
                <td>@include('partials.badge', ['group' => 'petition_status', 'value' => $p->status])</td>
            </tr>
        @empty
            <tr><td colspan="99" class="py-10 text-center text-slate-400">No petitions found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $petitions->links() }}</div>
@endsection
