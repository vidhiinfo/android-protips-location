@extends('layouts.app', ['pageTitle' => 'Dashboard'])

@section('content')
@php $u = auth()->user(); @endphp
<p class="mb-4 text-sm text-slate-500">Namaskar, {{ $u->name }} · {{ now()->format('l, '.setting('date_format')) }} · {{ $u->jurisdictionLabel() }}</p>

<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @php
        $tiles = [
            ['Office bearers', $totals['bearers'], route('members.index', ['type' => 'bearer'])],
            ['Party workers', $totals['workers'], route('members.index', ['type' => 'worker'])],
            ['Pending approval', $totals['proposed'], route('members.index', ['status' => 'proposed'])],
            ['Missing photo', $totals['missing_photo'], route('members.index', ['missing' => 'photo'])],
            ['Missing mobile', $totals['missing_mobile'], route('members.index', ['missing' => 'mobile'])],
            ['Added this month', $totals['added_this_month'], route('members.index', ['sort' => 'recent'])],
        ];
    @endphp
    @foreach ($tiles as [$label, $value, $href])
        <a href="{{ $u->canView('members') ? $href : '#' }}" class="card card-b block hover:ring-brand">
            <div class="text-xs font-medium text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ number_format($value) }}</div>
        </a>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-h">
            <span>{{ $breakdown['by'] }}-wise organisation status</span>
            <span class="text-xs font-normal text-slate-500">Posts = {{ strtolower($breakdown['by']) }}-level bearers vs sanctioned seats</span>
        </div>
        <div class="max-h-[32rem] overflow-auto">
            @php $maxB = max(1, collect($breakdown['rows'])->max('bearers')); @endphp
            <table class="table">
                <thead class="sticky top-0"><tr><th>{{ $breakdown['by'] }}</th><th>Bearers</th><th class="text-right">Workers</th><th>Posts filled</th><th class="text-right">No photo</th><th>Last update</th></tr></thead>
                <tbody>
                @foreach ($breakdown['rows'] as $r)
                    <tr>
                        <td class="font-medium">
                            <a class="hover:text-brand" href="{{ route('members.index', [$breakdown['by'] === 'State' ? 'state_id' : 'district_id' => $r['id']]) }}">{{ $r['name'] }}</a>
                        </td>
                        <td class="w-40">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 rounded bg-slate-100"><div class="h-2 rounded bg-brand" style="width: {{ round($r['bearers'] / $maxB * 100) }}%"></div></div>
                                <span class="w-8 text-right tabular-nums">{{ $r['bearers'] }}</span>
                            </div>
                        </td>
                        <td class="text-right tabular-nums">{{ $r['workers'] }}</td>
                        <td>
                            @if ($r['posts_sanctioned'])
                                <span class="{{ $r['vacancies'] ? 'badge-amber' : 'badge-green' }}">{{ $r['posts_filled'] }} / {{ $r['posts_sanctioned'] }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums {{ $r['no_photo'] ? 'text-amber-700' : 'text-slate-400' }}">{{ $r['no_photo'] }}</td>
                        <td class="text-xs text-slate-500">{{ $r['last_update'] ? \Illuminate\Support\Carbon::parse($r['last_update'])->diffForHumans() : 'No data' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        @if (! empty($office))
            <div class="card">
                <div class="card-h"><span>Today's schedule</span>@if ($u->canView('schedule'))<a href="{{ route('schedule.index') }}" class="text-xs font-normal text-brand">All →</a>@endif</div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($office['schedule'] as $s)
                        <li class="flex gap-3 px-4 py-2.5">
                            <span class="w-16 shrink-0 text-sm font-semibold text-brand tabular-nums">{{ $s->start_at->format('h:i A') }}</span>
                            <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $s->title }}</span><span class="block truncate text-xs text-slate-500">{{ $s->location }}</span></span>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-slate-400">No engagements today.</li>
                    @endforelse
                </ul>
                <div class="grid grid-cols-3 divide-x divide-slate-100 border-t border-slate-100 text-center text-xs">
                    @if (! is_null($office['visitors_today']))<a href="{{ route('visitors.index') }}" class="py-2.5"><b class="block text-lg text-slate-900">{{ $office['visitors_today'] }}</b>Visitors today</a>@endif
                    @if (! is_null($office['petitions_open']))<a href="{{ route('petitions.index') }}" class="py-2.5"><b class="block text-lg text-slate-900">{{ $office['petitions_open'] }}</b>Open petitions</a>@endif
                    @if (! is_null($office['petitions_overdue']))<a href="{{ route('petitions.index', ['overdue' => 1]) }}" class="py-2.5"><b class="block text-lg {{ $office['petitions_overdue'] ? 'text-red-600' : 'text-slate-900' }}">{{ $office['petitions_overdue'] }}</b>Overdue</a>@endif
                </div>
            </div>
        @endif

        @if ($birthdays->isNotEmpty())
            <div class="card">
                <div class="card-h"><span>🎂 Today's greetings</span><a href="{{ route('greetings') }}" class="text-xs font-normal text-brand">All →</a></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($birthdays->take(6) as $b)
                        <li class="flex items-center justify-between gap-2 px-4 py-2">
                            <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $b['name'] }}</span><span class="block truncate text-xs text-slate-500">{{ collect([$b['designation'], $b['area']])->filter()->implode(' · ') }}</span></span>
                            @if ($b['whatsapp_url'])<a href="{{ $b['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">WhatsApp</a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($myTasks->isNotEmpty())
            <div class="card">
                <div class="card-h"><span>My tasks</span><a href="{{ route('tasks.index') }}" class="text-xs font-normal text-brand">All →</a></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($myTasks as $t)
                        <li class="flex items-center justify-between gap-2 px-4 py-2 text-sm">
                            <span class="truncate">{{ $t->title }}</span>
                            <span class="text-xs whitespace-nowrap {{ $t->due_date?->isPast() ? 'text-red-600' : 'text-slate-500' }}">{{ fmt_date($t->due_date) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
