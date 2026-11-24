@extends('layouts.app', ['pageTitle' => $title])

@section('actions')
    @if ($canManage)
        <a href="{{ route($routeName.'.create') }}" class="btn-primary">+ Add {{ strtolower($singular) }}</a>
    @endif
@endsection

@section('content')
    <form class="no-print mb-4 flex flex-wrap items-end gap-2">
        <div class="min-w-48 flex-1">
            <label class="label">Search</label>
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Search…">
        </div>
        @foreach ($filters as $fname)
            <div>
                <label class="label">{{ $fields[$fname]['label'] }}</label>
                <select name="{{ $fname }}" class="input" data-autosubmit>
                    <option value="">All</option>
                    @foreach ($options[$fname] ?? [] as $k => $label)
                        <option value="{{ $k }}" @selected(request($fname) == $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        @if ($dateFilter)
            <div><label class="label">From</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
            <div><label class="label">To</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
        @endif
        <button class="btn-secondary">Filter</button>
        @if (request()->query())<a href="{{ route($routeName.'.index') }}?from=" class="btn text-slate-500">Clear</a>@endif
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr>
                @foreach ($fields as $name => $f) @if ($f['list'] ?? false)<th>{{ $f['label'] }}</th>@endif @endforeach
                <th class="w-1"></th>
            </tr></thead>
            <tbody>
            @forelse ($rows as $record)
                <tr>
                    @foreach ($fields as $name => $f)
                        @if ($f['list'] ?? false)<td>@include('partials.value')</td>@endif
                    @endforeach
                    <td class="text-right whitespace-nowrap">
                        @if ($routeName === 'tasks' && ! $canManage)
                            <form method="POST" action="{{ route('tasks.status', $record->id) }}" class="inline-flex gap-1">
                                @csrf @method('PATCH')
                                <select name="status" class="input py-1 text-xs" data-autosubmit>
                                    @foreach ($options['status'] as $k => $l)<option value="{{ $k }}" @selected($record->status === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </form>
                        @endif
                        @if ($canManage)
                            <a href="{{ route($routeName.'.edit', $record->id) }}" class="btn-secondary btn-sm">Edit</a>
                        @endif
                    </td>
                </tr>
                @if (($record->notes ?? $record->details ?? null) && $routeName !== 'contacts')
                    <tr><td colspan="99" class="border-t-0 pt-0 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($record->notes ?? $record->details, 200) }}</td></tr>
                @endif
            @empty
                <tr><td colspan="99" class="py-10 text-center text-slate-400">Nothing here yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
