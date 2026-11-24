@extends('layouts.app', ['pageTitle' => $program->name])

@section('actions')
    @if (auth()->user()->canManage('programs'))<a href="{{ route('programs.edit', $program) }}" class="btn-secondary">Edit</a>@endif
@endsection

@section('content')
@php $u = auth()->user(); $districtJson = $districtsByState->map(fn ($ds) => $ds->map(fn ($d) => [$d->id, $d->name])->values()); @endphp
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <div class="card card-b">
            <div class="text-xs text-slate-500">{{ fmt_date($program->start_date) }} @if ($program->end_date) – {{ fmt_date($program->end_date) }} @endif</div>
            <p class="mt-2 text-sm whitespace-pre-line">{{ $program->description }}</p>
        </div>

        <div class="card">
            <div class="card-h">Reports ({{ $reports->total() }})</div>
            <div class="divide-y divide-slate-100">
                @forelse ($reports as $r)
                    <div class="px-4 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div class="font-medium">{{ collect([$r->district?->name, $r->state?->name])->filter()->implode(', ') }} <span class="text-sm font-normal text-slate-500">· {{ $r->venue }}</span></div>
                            <div class="text-xs text-slate-500">{{ fmt_date($r->event_date) }} · {{ number_format((int) $r->attendance) }} attended</div>
                        </div>
                        @if ($r->summary)<p class="mt-1 text-sm text-slate-600">{{ $r->summary }}</p>@endif
                        @if ($r->photos)
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($r->photos as $i => $_)
                                    <a href="{{ route('programs.reports.photo', [$r, $i]) }}" target="_blank"><img src="{{ route('programs.reports.photo', [$r, $i]) }}" class="h-20 w-28 rounded-md object-cover ring-1 ring-slate-200" loading="lazy" alt=""></a>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-1 text-xs text-slate-400">Submitted by {{ $r->submitter?->name }}</div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-400">No reports yet.</div>
                @endforelse
            </div>
        </div>
        {{ $reports->links() }}
    </div>

    <div class="space-y-6">
        <form method="POST" enctype="multipart/form-data" action="{{ route('programs.reports.store', $program) }}" class="card">
            @csrf
            <div class="card-h">Submit a report</div>
            <div class="card-b grid gap-3">
                <div>
                    <label class="label">State</label>
                    <select name="state_id" class="input" data-state-select="#r_district" @if (! $u->isNational()) disabled @endif>
                        @foreach ($states as $s)<option value="{{ $s->id }}" @selected($u->state_id == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                    @if (! $u->isNational())<input type="hidden" name="state_id" value="{{ $u->state_id }}">@endif
                </div>
                <div><label class="label">District</label><select name="district_id" id="r_district" class="input" data-districts='@json($districtJson)' data-value="{{ $u->district_id }}" data-placeholder="State-level event"></select></div>
                <div><label class="label">Event date *</label><input type="date" name="event_date" value="{{ today()->format('Y-m-d') }}" class="input" required></div>
                <div><label class="label">Venue</label><input name="venue" class="input"></div>
                <div><label class="label">Attendance</label><input type="number" min="0" name="attendance" class="input"></div>
                <div><label class="label">Summary</label><textarea name="summary" rows="3" class="input"></textarea></div>
                <div><label class="label">Photos (up to 10)</label><input type="file" name="photos[]" accept="image/*" multiple class="input"></div>
                <button class="btn-primary">Submit report</button>
            </div>
        </form>

        @if ($coverage)
            <div class="card">
                <div class="card-h">State coverage <span class="text-sm font-normal text-slate-500">{{ $coverage->where('reports', '>', 0)->count() }} / {{ $coverage->count() }} reported</span></div>
                <ul class="max-h-96 divide-y divide-slate-100 overflow-auto text-sm">
                    @foreach ($coverage->sortByDesc('reports') as $c)
                        <li class="flex justify-between px-4 py-1.5">
                            <a href="{{ route('programs.show', [$program, 'state_id' => $c['state']->id]) }}" class="{{ $c['reports'] ? '' : 'text-slate-400' }}">{{ $c['state']->name }}</a>
                            <span class="tabular-nums {{ $c['reports'] ? 'font-medium' : 'text-red-500' }}">{{ $c['reports'] ?: 'Pending' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
