@extends('layouts.app', ['pageTitle' => 'Programmes & Campaign Reports'])

@section('actions')
    @if (auth()->user()->canManage('programs'))<a href="{{ route('programs.create') }}" class="btn-primary">+ New programme</a>@endif
@endsection

@section('content')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($programs as $p)
        <a href="{{ route('programs.show', $p) }}" class="card card-b block hover:ring-brand">
            <div class="font-semibold text-slate-900">{{ $p->name }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ fmt_date($p->start_date) }} @if ($p->end_date) – {{ fmt_date($p->end_date) }} @endif</div>
            <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $p->description }}</p>
            <div class="mt-3 text-sm"><b>{{ $p->reports_count }}</b> <span class="text-slate-500">reports received</span></div>
        </a>
    @empty
        <div class="card card-b col-span-full py-10 text-center text-slate-400">No programmes yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $programs->links() }}</div>
@endsection
