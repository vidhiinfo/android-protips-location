@extends('layouts.app', ['pageTitle' => 'Office Bearers & Party Workers'])

@section('actions')
    @if (auth()->user()->canManage('import'))
        <a href="{{ route('members.export', request()->query()) }}" class="btn-secondary hidden sm:inline-flex">⬇ Export CSV</a>
    @endif
    @if (auth()->user()->canManage('members'))
        <a href="{{ route('members.create') }}" class="btn-primary">+ Add member</a>
    @endif
@endsection

@section('content')
<form class="no-print card card-b mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
    <div class="col-span-2">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Name, mobile, mandal…">
    </div>
    @include('members._geo-filters')
    <div>
        <label class="label">Level</label>
        <select name="level" class="input"><option value="">All</option>
            @foreach ($levels as $k => $l)<option value="{{ $k }}" @selected(request('level') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label">Type</label>
        <select name="type" class="input"><option value="">All</option>
            @foreach ($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label">Status</label>
        <select name="status" class="input"><option value="">Active + proposed</option>
            @foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label">Data quality</label>
        <select name="missing" class="input"><option value="">Any</option>
            @foreach (['photo' => 'Missing photo', 'mobile' => 'Missing mobile', 'dob' => 'Missing DOB', 'consent' => 'No consent'] as $k => $l)
                <option value="{{ $k }}" @selected(request('missing') === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-2 flex items-end gap-2 md:col-span-4 xl:col-span-8">
        <button class="btn-primary">Apply filters</button>
        <a href="{{ route('members.index') }}" class="btn-secondary">Reset</a>
        <span class="ml-auto text-sm text-slate-500">{{ number_format($members->total()) }} found</span>
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Designation</th><th>Area</th><th>Mobile</th>
            @foreach ($listFields as $cf)<th>{{ $cf->label }}</th>@endforeach
            <th>Status</th><th>Profile</th></tr></thead>
        <tbody>
        @forelse ($members as $m)
            <tr>
                <td>
                    <a href="{{ route('members.show', $m) }}" class="flex items-center gap-3">
                        @include('partials.avatar', ['member' => $m, 'size' => 'h-9 w-9 text-xs'])
                        <span><span class="block font-medium text-slate-900 hover:text-brand">{{ $m->name }}</span>
                        <span class="block text-xs text-slate-500">{{ lookup_label('member_type', $m->type) }}</span></span>
                    </a>
                </td>
                <td>{{ $m->designation?->title }}<div class="text-xs text-slate-500">{{ lookup_label('level', $m->level) }}</div></td>
                <td class="text-sm">{{ $m->area() }}</td>
                <td class="whitespace-nowrap">@if ($m->mobile)<a href="tel:{{ $m->mobile }}" class="hover:text-brand">{{ $m->mobile }}</a>@endif</td>
                @foreach ($listFields as $cf)<td>{{ $m->extra[$cf->key] ?? '' }}</td>@endforeach
                <td>@include('partials.badge', ['group' => 'member_status', 'value' => $m->status])</td>
                <td>
                    @php $c = $m->completeness(); @endphp
                    <div class="flex items-center gap-1.5" title="{{ $c }}% complete">
                        <div class="h-1.5 w-12 rounded bg-slate-100"><div class="h-1.5 rounded {{ $c >= 80 ? 'bg-green-500' : ($c >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $c }}%"></div></div>
                        <span class="text-xs text-slate-500">{{ $c }}%</span>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="99" class="py-10 text-center text-slate-400">No members match these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $members->links() }}</div>
@endsection
