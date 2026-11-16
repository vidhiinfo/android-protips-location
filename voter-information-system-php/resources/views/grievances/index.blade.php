@extends('layouts.app')
@section('title', t('gr.title'))
@section('content')
@php($stCls = ['OPEN' => 'text-bg-danger', 'IN_PROGRESS' => 'text-bg-warning', 'RESOLVED' => 'text-bg-success', 'CLOSED' => 'text-bg-secondary'])
@php($prCls = ['URGENT' => 'text-bg-danger', 'HIGH' => 'text-bg-warning', 'NORMAL' => 'text-bg-light border', 'LOW' => 'text-bg-light border text-muted'])
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-megaphone"></i> {{ t('gr.title') }}</h4><div class="small text-muted">{{ t('gr.intro') }}</div></div>
        @if(can('REPORT_VIEW'))<a class="btn btn-sm btn-outline-secondary" href="{{ url('/reports/grievances') }}?assemblyNo={{ $assemblyNo }}"><i class="bi bi-graph-up"></i> {{ t('report.grievances') }}</a>@endif
        <a class="btn btn-sm btn-primary" href="{{ url('/grievances/new') }}?assemblyNo={{ $assemblyNo }}"><i class="bi bi-plus-lg"></i> {{ t('gr.add') }}</a>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach(array_merge(['ACTIVE'], \App\Models\Grievance::STATUSES, ['ALL']) as $st)
        <a class="btn btn-sm {{ $f['status'] === $st ? 'btn-dark' : 'btn-outline-secondary' }}" href="?{{ http_build_query(array_merge(array_filter($f), ['status' => $st])) }}">
            {{ t('gr.status.'.$st) }} <span class="badge text-bg-light">{{ $st === 'ACTIVE' ? ($counts['OPEN'] ?? 0) + ($counts['IN_PROGRESS'] ?? 0) : ($st === 'ALL' ? $counts->sum() : ($counts[$st] ?? 0)) }}</span></a>
        @endforeach
    </div>
    <form method="get" class="card card-body py-2 mb-3"><input type="hidden" name="status" value="{{ $f['status'] }}"><div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-0">{{ t('common.search') }}</label><input name="q" value="{{ $f['q'] }}" class="form-control form-control-sm" placeholder="{{ t('gr.searchHint') }}"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('gr.category') }}</label><select name="category" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
            @foreach(\App\Models\Grievance::CATEGORIES as $c)<option value="{{ $c }}" @selected($c === $f['category'])>{{ t('gr.cat.'.$c) }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
            @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $f['boothNo'])>{{ $b }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">{{ t('gr.assigned') }}</label><select name="workerId" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
            @foreach($workers as $w)<option value="{{ $w->id }}" @selected($w->id == $f['workerId'])>{{ $w->label() }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> {{ t('common.apply') }}</button></div>
    </div></form>
    <div class="table-responsive bg-white border rounded"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>{{ t('gr.subject') }}</th><th>{{ t('gr.category') }}</th><th>{{ t('col.name') }}</th><th>{{ t('col.boothNo') }}</th>
            <th>{{ t('gr.priority') }}</th><th>{{ t('gr.status') }}</th><th>{{ t('gr.assigned') }}</th><th class="text-end">{{ t('gr.days') }}</th></tr></thead>
        <tbody>@forelse($page as $g)
            <tr><td><a href="{{ url('/grievances/'.$g->id) }}">{{ $g->id }}</a></td>
                <td><a href="{{ url('/grievances/'.$g->id) }}" class="fw-semibold">{{ $g->subject }}</a><div class="small text-muted">{{ $g->created_at?->format('d-m-Y') }}</div></td>
                <td>{{ t('gr.cat.'.$g->category) }}</td>
                <td>@if($g->voter_id)<a href="{{ url('/voters/'.$g->voter_id) }}">{{ $g->name }}</a>@else{{ $g->name }}@endif @if($g->mobile)<div class="small"><a href="tel:{{ $g->mobile }}">{{ $g->mobile }}</a></div>@endif</td>
                <td>{{ $g->booth_no }}</td><td><span class="badge {{ $prCls[$g->priority] }}">{{ t('gr.pr.'.$g->priority) }}</span></td>
                <td><span class="badge {{ $stCls[$g->status] }}">{{ t('gr.status.'.$g->status) }}</span></td>
                <td class="small">{{ $g->worker?->name }}</td><td class="text-end {{ $g->isOpen() && $g->ageDays() > 7 ? 'text-danger fw-semibold' : '' }}">{{ $g->ageDays() }}</td></tr>
        @empty <tr><td colspan="9" class="text-center text-muted py-4">{{ t('gr.none') }}</td></tr> @endforelse</tbody></table></div>
    <div class="mt-3">{{ $page->links() }}</div>
</div>
@endsection
