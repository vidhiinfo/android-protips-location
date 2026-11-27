@extends('layouts.app')
@section('title', t('worker.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-person-badge"></i> {{ t('worker.title') }}</h4><div class="small text-muted">{{ t('worker.intro') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @endunless
            <div><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
            <div><label class="form-label small mb-0">{{ t('worker.role') }}</label><select name="role" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach(\App\Support\WorkerRoles::codes() as $ro)<option value="{{ $ro }}" @selected($ro === $role)>{{ role_label($ro) }}</option>@endforeach</select></div>
        </form>
        <a class="btn btn-sm btn-primary" href="{{ url('/workers/new') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}"><i class="bi bi-plus-lg"></i> {{ t('worker.add') }}</a>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat"><div class="small text-muted">{{ t('worker.count') }}</div><div class="num">{{ $workers->where('active', true)->count() }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat stat-link position-relative"><div class="small text-muted">{{ t('worker.unassignedVoters') }}</div>
            <div class="num"><a class="stretched-link text-decoration-none" href="{{ url('/voters') }}?{{ http_build_query(array_filter(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo, 'workerId' => 0, 'deleted' => 'exclude'], fn($v) => $v !== null)) }}">{{ $unassigned }}</a></div></div></div>
        @if($boothNo)
        <div class="col-md-6"><form method="post" action="{{ url('/workers/panna') }}" class="card card-body py-2 h-100" data-confirm="{{ t('worker.splitConfirm') }}">@csrf
            <input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}"><input type="hidden" name="boothNo" value="{{ $boothNo }}">
            <div class="small fw-semibold"><i class="bi bi-diagram-3"></i> {{ t('worker.splitTitle', $boothNo) }}</div>
            <div class="small text-muted mb-1">{{ t('worker.splitHint') }}</div>
            <div class="d-flex gap-3 align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="replace" value="1" id="rep"><label class="form-check-label small" for="rep">{{ t('worker.replace') }}</label></div>
                <button class="btn btn-sm btn-outline-primary ms-auto">{{ t('worker.split.go') }}</button></div>
        </form></div>
        @endif
    </div>
    <div class="table-responsive bg-white border rounded"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>{{ t('worker.name') }}</th><th>{{ t('worker.role') }}</th><th>{{ t('col.boothNo') }}</th><th>{{ t('col.mobile') }}</th>
            <th class="text-end">{{ t('dash.voters') }}</th><th class="text-end">{{ t('an.households') }}</th><th style="min-width:160px">{{ t('worker.surveyed') }}</th>
            <th class="text-end">{{ t('an.support') }}</th><th class="text-end">{{ t('report.mobilePct') }}</th><th class="text-end">{{ t('worker.voted') }}</th><th>{{ t('worker.login') }}</th></tr></thead>
        <tbody>
        @forelse($workers as $i => $w)
        @php($s = $stats[$w->id] ?? ['n' => 0, 'surveyed' => 0, 'mobile' => 0, 'support' => 0, 'voted' => 0])
        @php($pct = $s['n'] ? round(100 * $s['surveyed'] / $s['n']) : 0)
        <tr class="{{ $w->active ? '' : 'text-muted' }}">
            <td>{{ $i + 1 }}@if($i < 3 && $s['n'] && $w->active) <i class="bi bi-award-fill text-warning"></i>@endif</td>
            <td><a href="{{ url('/workers/'.$w->id) }}" class="fw-semibold">{{ $w->name }}</a>@unless($w->active) <span class="badge text-bg-secondary">{{ t('worker.inactive') }}</span>@endunless</td>
            <td><span class="badge text-bg-light border">{{ role_label($w->role) }}</span></td><td>{{ $w->booth_no }}</td>
            <td>@if($w->mobile)<a href="tel:{{ $w->mobile }}">{{ $w->mobile }}</a>@endif</td>
            <td class="text-end">@if($s['n'])<a href="{{ url('/voters') }}?workerId={{ $w->id }}">{{ $s['n'] }}</a>@else 0 @endif</td>
            <td class="text-end">{{ $s['houses'] ?? 0 }}</td>
            <td><div class="d-flex align-items-center gap-2"><div class="progress flex-fill" style="height:8px"><div class="progress-bar {{ $pct >= 80 ? 'bg-success' : ($pct >= 40 ? '' : 'bg-warning') }}" style="width:{{ $pct }}%"></div></div><span class="small">{{ $pct }}%</span></div></td>
            <td class="text-end">{{ $s['support'] }}</td><td class="text-end">{{ $s['n'] ? round(100 * $s['mobile'] / $s['n']) : 0 }}%</td>
            <td class="text-end">{{ $s['voted'] }}</td><td class="small">{{ $w->username }}</td>
        </tr>
        @empty
        <tr><td colspan="12" class="text-center text-muted py-4">{{ t('worker.none') }}</td></tr>
        @endforelse
        </tbody></table></div>
</div>
@endsection
