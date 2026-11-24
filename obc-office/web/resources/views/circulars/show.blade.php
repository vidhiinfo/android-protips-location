@extends('layouts.app', ['pageTitle' => 'Circular'])

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <article class="card lg:col-span-2">
        <div class="card-h"><span>{{ $circular->title }}</span><span class="badge-gray">{{ $circular->targetState?->name ?? 'All states' }}</span></div>
        <div class="card-b">
            <div class="text-xs text-slate-500">{{ fmt_datetime($circular->created_at) }}</div>
            <div class="mt-3 text-sm leading-relaxed whitespace-pre-line">{{ $circular->body }}</div>
            @if ($circular->attachment_path)<a href="{{ route('circulars.attachment', $circular) }}" class="btn-secondary mt-4">📎 Download attachment</a>@endif
        </div>
    </article>
    @if ($readStats)
        <div class="card">
            <div class="card-h">Read receipts <span class="text-sm font-normal text-slate-500">{{ $readStats->where('read', true)->count() }} / {{ $readStats->count() }}</span></div>
            <ul class="max-h-[28rem] divide-y divide-slate-100 overflow-auto text-sm">
                @foreach ($readStats->sortBy('read') as $r)
                    <li class="flex items-center justify-between px-4 py-2">
                        <span class="min-w-0"><span class="block truncate">{{ $r['user']->name }}</span><span class="block truncate text-xs text-slate-500">{{ $r['user']->jurisdictionLabel() }}</span></span>
                        {!! $r['read'] ? '<span class="badge-green">Read</span>' : '<span class="badge-amber">Unread</span>' !!}
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('circulars.destroy', $circular) }}" data-confirm="Delete this circular?" class="border-t border-slate-100 p-3 text-right">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete circular</button></form>
        </div>
    @endif
</div>
@endsection
