@extends('layouts.app', ['pageTitle' => 'Circulars & Directives'])

@section('actions')
    @if (auth()->user()->canManage('circulars'))<a href="{{ route('circulars.create') }}" class="btn-primary">+ Issue circular</a>@endif
@endsection

@section('content')
<div class="card divide-y divide-slate-100">
    @forelse ($circulars as $c)
        @php $read = $c->readers->isNotEmpty(); @endphp
        <a href="{{ route('circulars.show', $c) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $read ? 'bg-transparent' : 'bg-brand' }}"></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate {{ $read ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $c->title }}</span>
                <span class="block truncate text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($c->body, 140) }}</span>
            </span>
            <span class="shrink-0 text-right text-xs text-slate-500">
                {{ fmt_date($c->created_at) }}<br>
                <span class="badge-gray">{{ $c->targetState?->name ?? 'All states' }}</span>
                @if (auth()->user()->canManage('circulars'))<span class="block mt-1">👁 {{ $c->readers_count }}</span>@endif
            </span>
        </a>
    @empty
        <div class="px-4 py-10 text-center text-slate-400">No circulars yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $circulars->links() }}</div>
@endsection
