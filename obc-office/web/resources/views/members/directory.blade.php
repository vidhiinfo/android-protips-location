@extends('layouts.app', ['pageTitle' => 'Photo Directory'])

@section('actions')
    @if ($members)<button onclick="window.print()" class="btn-primary">🖨 Print / Save PDF</button>@endif
@endsection

@section('content')
<form class="no-print card card-b mb-6 grid grid-cols-2 items-end gap-3 md:grid-cols-6">
    @include('members._geo-filters')
    <div>
        <label class="label">Level</label>
        <select name="level" class="input"><option value="">All levels</option>
            @foreach ($levels as $k => $l)<option value="{{ $k }}" @selected(request('level') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label">Type</label>
        <select name="type" class="input">
            @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type', 'bearer') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="col-span-2 md:col-span-2"><button class="btn-primary w-full">Generate directory</button></div>
</form>

@if (is_null($members))
    <div class="card card-b py-12 text-center text-slate-500">Choose a state, district or level and click <b>Generate directory</b> to build a printable photo directory.</div>
@else
    @php
        $titleParts = array_filter([
            request('level') ? ($levels[request('level')] ?? '') : null,
            $states->firstWhere('id', request('state_id'))?->name,
            optional($districtsByState->flatten()->firstWhere('id', request('district_id')))->name,
        ]);
    @endphp
    <div class="mb-6 text-center">
        <div class="text-xl font-bold text-slate-900">{{ setting('org_name') }}</div>
        <div class="text-sm text-slate-600">{{ $types[request('type', 'bearer')] ?? '' }} directory {{ $titleParts ? '— '.implode(' · ', $titleParts) : '' }}</div>
        <div class="text-xs text-slate-400">Generated {{ fmt_datetime(now()) }} · {{ $members->count() }} people</div>
    </div>
    @forelse ($members->groupBy(fn ($m) => $m->area()) as $area => $group)
        <h3 class="mt-6 mb-3 border-b-2 border-brand pb-1 text-sm font-bold tracking-wide text-slate-700 uppercase" style="break-after: avoid">{{ $area }}</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 print:grid-cols-5">
            @foreach ($group as $m)
                <div class="card overflow-hidden text-center" style="break-inside: avoid">
                    <div class="aspect-[4/5] bg-slate-100">
                        @if ($m->photo_url)
                            <img src="{{ $m->photo_url }}" class="h-full w-full object-cover" alt="">
                        @else
                            <div class="flex h-full items-center justify-center text-3xl font-semibold text-slate-300">{{ $m->initials() }}</div>
                        @endif
                    </div>
                    <div class="p-2">
                        <div class="truncate text-sm font-semibold">{{ $m->name }}</div>
                        <div class="truncate text-xs font-medium text-brand">{{ $m->designation?->title }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $m->mobile }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="card card-b py-10 text-center text-slate-400">No active members for this selection.</div>
    @endforelse
    <p class="mt-8 text-center text-xs text-slate-400">{{ setting('footer_text') }}</p>
@endif
@endsection
