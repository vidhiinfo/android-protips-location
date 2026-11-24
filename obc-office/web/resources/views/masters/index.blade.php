@extends('layouts.app', ['pageTitle' => 'States, Districts & Posts'])

@section('content')
<div class="grid gap-6 xl:grid-cols-2">
    <div class="space-y-6">
        <div class="card">
            <div class="card-h">States & UTs ({{ $states->count() }})</div>
            <div class="max-h-96 overflow-auto">
                <table class="table">
                    <thead class="sticky top-0"><tr><th>Name</th><th>Code</th><th class="text-right">Districts</th><th class="text-right">Members</th></tr></thead>
                    <tbody>
                    @foreach ($states as $s)
                        <tr class="{{ $selectedState?->id === $s->id ? 'bg-brand/5' : '' }}">
                            <td><a href="{{ route('masters.index', ['state_id' => $s->id]) }}" class="font-medium hover:text-brand">{{ $s->name }}</a> @if ($s->is_ut)<span class="badge-gray">UT</span>@endif</td>
                            <td class="text-xs">{{ $s->code }}</td>
                            <td class="text-right tabular-nums">{{ $s->districts_count }}</td>
                            <td class="text-right tabular-nums">{{ $s->members_count }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('masters.states.store') }}" class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-3">@csrf
                <div class="flex-1"><label class="label">Add state / UT</label><input name="name" class="input" required></div>
                <div class="w-20"><label class="label">Code</label><input name="code" class="input"></div>
                <label class="flex items-center gap-1 pb-2 text-sm"><input type="checkbox" name="is_ut" value="1"> UT</label>
                <button class="btn-secondary">Add</button>
            </form>
        </div>

        @if ($selectedState)
            <div class="card">
                <div class="card-h">Districts of {{ $selectedState->name }} ({{ $selectedState->districts->count() }})</div>
                <ul class="max-h-80 divide-y divide-slate-100 overflow-auto">
                    @foreach ($selectedState->districts as $d)
                        <li class="flex items-center gap-2 px-4 py-1.5">
                            <form method="POST" action="{{ route('masters.districts.update', $d) }}" class="flex flex-1 gap-2">@csrf @method('PUT')
                                <input name="name" value="{{ $d->name }}" class="input py-1 text-sm"><button class="btn-secondary btn-sm">Rename</button>
                            </form>
                            <form method="POST" action="{{ route('masters.districts.destroy', $d) }}" data-confirm="Delete {{ $d->name }}?">@csrf @method('DELETE')<button class="btn-danger btn-sm">✕</button></form>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('masters.districts.store', $selectedState) }}" class="border-t border-slate-100 p-3">@csrf
                    <label class="label">Add districts — one per line (or comma separated)</label>
                    <textarea name="names" rows="4" class="input" placeholder="Bareilly&#10;Moradabad&#10;Aligarh"></textarea>
                    <button class="btn-primary mt-2">Add districts</button>
                </form>
            </div>
        @else
            <div class="card card-b text-sm text-slate-500">Select a state to manage its districts.</div>
        @endif
    </div>

    <div class="card">
        <div class="card-h">Designations (posts) per level</div>
        <div class="card-b space-y-5">
            <p class="text-xs text-slate-500"><b>Seniority</b> controls ordering in lists and the photo directory (1 = top). <b>Seats</b> is the sanctioned number of posts per unit, used for vacancy tracking on the dashboard.</p>
            @foreach ($levels as $lvl => $lvlLabel)
                <div>
                    <div class="mb-1 text-sm font-semibold text-slate-700">{{ $lvlLabel }}</div>
                    <div class="space-y-1">
                        @foreach ($designations[$lvl] ?? [] as $d)
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('masters.designations.update', $d) }}" class="flex flex-1 items-center gap-2">@csrf @method('PUT')
                                    <input name="title" value="{{ $d->title }}" class="input flex-1 py-1 text-sm">
                                    <input name="seniority" type="number" value="{{ $d->seniority }}" class="input w-16 py-1 text-sm" title="Seniority">
                                    <input name="max_count" type="number" value="{{ $d->max_count }}" class="input w-16 py-1 text-sm" title="Seats">
                                    <button class="btn-secondary btn-sm">Save</button>
                                </form>
                                <form method="POST" action="{{ route('masters.designations.destroy', $d) }}" data-confirm="Delete post {{ $d->title }}?">@csrf @method('DELETE')<button class="btn-danger btn-sm">✕</button></form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('masters.designations.store') }}" class="grid grid-cols-2 items-end gap-2 border-t border-slate-100 p-3 sm:grid-cols-5">@csrf
            <div><label class="label">Level</label><select name="level" class="input">@foreach ($levels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="label">New post title</label><input name="title" class="input" required></div>
            <div><label class="label">Seniority / Seats</label><div class="flex gap-1"><input name="seniority" type="number" value="50" class="input"><input name="max_count" type="number" value="1" class="input"></div></div>
            <button class="btn-primary">Add post</button>
        </form>
    </div>
</div>
@endsection
