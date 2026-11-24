@extends('layouts.app', ['pageTitle' => 'Biodata Register'])

@section('actions')
    <a href="{{ route('biodata.export', request()->query()) }}" class="btn-secondary hidden sm:inline-flex">⬇ Export CSV</a>
    @if (auth()->user()->canManage('biodata'))<a href="{{ route('biodata.create') }}" class="btn-primary">+ Record biodata</a>@endif
@endsection

@section('content')
<form class="no-print card card-b mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
    <div class="col-span-2">
        <label class="label">Search</label>
        <input name="q" value="{{ request('q') }}" class="input" placeholder="Name, mobile, ref. no., received from…">
    </div>
    @include('members._geo-filters')
    @foreach (['category' => ['Category', $categories], 'received_via' => ['Received via', $sources], 'status' => ['Status', $statuses]] as $f => [$label, $opts])
        <div>
            <label class="label">{{ $label }}</label>
            <select name="{{ $f }}" class="input"><option value="">All</option>
                @foreach ($opts as $k => $l)<option value="{{ $k }}" @selected(request($f) === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
    @endforeach
    <div><label class="label">Received from date</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
    <div><label class="label">Received to date</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
    <div class="col-span-2 flex items-end gap-2 md:col-span-2 xl:col-span-6">
        <button class="btn-primary">Apply filters</button>
        <a href="{{ route('biodata.index') }}" class="btn-secondary">Reset</a>
        <span class="ml-auto text-sm text-slate-500">{{ number_format($rows->total()) }} found · {{ $thisMonth }} received this month</span>
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Ref. no.</th><th>Name</th><th>Area</th><th>Mobile</th><th>Received</th><th>Category</th>
            @foreach ($listFields as $cf)<th>{{ $cf->label }}</th>@endforeach
            <th>Files</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($rows as $b)
            <tr>
                <td><a href="{{ route('biodata.show', $b) }}" class="font-mono text-xs font-semibold whitespace-nowrap text-brand hover:underline">{{ $b->reference_no }}</a></td>
                <td>
                    <a href="{{ route('biodata.show', $b) }}" class="flex items-center gap-3">
                        <span class="avatar h-9 w-9 text-xs">@if ($b->photoUrl())<img src="{{ $b->photoUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">@else{{ $b->initials() }}@endif</span>
                        <span><span class="block font-medium text-slate-900 hover:text-brand">{{ $b->name }}</span>
                            @if ($b->father_name)<span class="block text-xs text-slate-500">S/o, D/o, W/o {{ $b->father_name }}</span>@endif</span>
                    </a>
                </td>
                <td class="text-sm">{{ $b->area() ?: '—' }}</td>
                <td class="whitespace-nowrap">{{ $b->mobile }}</td>
                <td class="text-xs whitespace-nowrap">{{ fmt_date($b->received_on) }}<div class="max-w-40 truncate text-slate-500">{{ $b->received_from }}</div></td>
                <td class="text-xs">{{ lookup_label('biodata_category', $b->category) }}</td>
                @foreach ($listFields as $cf)<td class="text-sm">{{ $b->extra[$cf->key] ?? '' }}</td>@endforeach
                <td class="text-center text-sm">{{ $b->documents_count ? '📎 '.$b->documents_count : '—' }}</td>
                <td>@include('partials.badge', ['group' => 'biodata_status', 'value' => $b->status])</td>
            </tr>
        @empty
            <tr><td colspan="99" class="py-10 text-center text-slate-400">No biodata found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
