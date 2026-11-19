@extends('layouts.app')
@section('title', t('roll.title'))
@section('content')
@php($cls = ['NEW' => 'success', 'DELETED' => 'danger', 'SHIFTED' => 'warning', 'CHANGED' => 'primary', 'NAMEMATCH' => 'info', 'RESTORED' => 'secondary', 'SAME' => 'light'])
@php($stCls = ['PREVIEW' => 'text-bg-warning', 'APPLIED' => 'text-bg-success', 'UNDONE' => 'text-bg-secondary', 'DISCARDED' => 'text-bg-light border'])
@php($act = $c['actions'] ?? [])
@php($errs = $u->errorList())
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="me-auto">
            <h4 class="mb-0"><i class="bi bi-arrow-repeat"></i> {{ t('roll.title') }}: {{ t('col.assemblyNo') }} {{ $u->assembly_no }}
                <span class="badge {{ $stCls[$u->status] ?? '' }} fs-6 align-middle">{{ t('roll.status.' . $u->status) }}</span></h4>
            <div class="small text-muted">{{ $u->file_name }} · {{ $u->created_at?->format('d-m-Y H:i') }} · {{ $u->created_by }}
                @if($u->applied_at) · {{ t('roll.appliedOn', $u->applied_at->format('d-m-Y H:i'), $u->applied_by) }}@endif
                · {{ t('roll.boothsInFile', count($u->boothList())) }}</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/roll-update/' . $u->id . '/report') }}"><i class="bi bi-file-earmark-excel"></i> {{ t('roll.report') }}</a>
        @if($u->status === 'PREVIEW')
            <form method="post" action="{{ url('/roll-update/' . $u->id . '/discard') }}" onsubmit="return confirm(@js(t('roll.discardConfirm')))">@csrf
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> {{ t('roll.discard') }}</button></form>
            <form method="post" action="{{ url('/roll-update/' . $u->id . '/apply') }}" onsubmit="return confirm(@js(t('roll.applyConfirm')))">@csrf
                <button class="btn btn-sm btn-success" data-busy="{{ t('roll.applying') }}"><i class="bi bi-check2-circle"></i> {{ t('roll.apply') }}</button></form>
        @elseif($canUndo)
            <form method="post" action="{{ url('/roll-update/' . $u->id . '/undo') }}" onsubmit="return confirm(@js(t('roll.undoConfirm')))">@csrf
                <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i> {{ t('roll.undo') }}</button></form>
        @endif
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/roll-update') }}">{{ t('common.back') }}</a>
    </div>

    @if($result)
        <div class="alert {{ $result['failed'] ? 'alert-warning' : 'alert-success' }} py-2">
            {{ t('roll.result', number_format($result['updated']), number_format($result['added']), number_format($result['deleted'])) }}
            @if($result['pannaCleared']) {{ t('roll.pannaCleared', number_format($result['pannaCleared'])) }}@endif
            @if($result['failed'])<div class="fw-semibold mt-1">{{ t('roll.failed', count($result['failed'])) }}</div>
                <ul class="small mb-0">@foreach(array_slice($result['failed'], 0, 20) as $f)<li>{{ $f }}</li>@endforeach</ul>@endif
        </div>
    @elseif($u->status === 'PREVIEW')
        <div class="alert alert-warning py-2 small"><i class="bi bi-eye"></i> {{ t('roll.previewNote') }}</div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3 col-xl"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">{{ t('roll.inRoll') }}</div>
            <div class="fs-4 fw-bold">{{ number_format($c['inRoll'] ?? 0) }}</div></div></div></div>
        @foreach(['NEW', 'DELETED', 'SHIFTED', 'CHANGED', 'NAMEMATCH', 'RESTORED', 'SAME'] as $a)
        <div class="col-6 col-md-3 col-xl"><a class="card h-100 text-decoration-none border-{{ $cls[$a] }} {{ $action === $a ? 'shadow' : '' }}" href="?action={{ $a }}">
            <div class="card-body py-2"><div class="small text-muted">{{ t('roll.action.' . $a) }}</div>
            <div class="fs-4 fw-bold text-{{ $a === 'SAME' ? 'body' : $cls[$a] }}">{{ number_format($act[$a] ?? 0) }}</div>
            <div class="small text-muted lh-sm">{{ t('roll.help.' . $a) }}</div></div></a></div>
        @endforeach
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-6"><div class="alert alert-success py-2 mb-0 h-100"><i class="bi bi-shield-check"></i> {{ t('roll.surveyKept', number_format($c['surveyKept'] ?? 0)) }}</div></div>
        <div class="col-md-6"><a href="?action=DELETED&survey=1" class="alert {{ ($c['surveyDeleted'] ?? 0) ? 'alert-danger' : 'alert-light border' }} py-2 mb-0 h-100 d-block text-decoration-none">
            <i class="bi bi-exclamation-triangle"></i> {{ t('roll.surveyDeleted', number_format($c['surveyDeleted'] ?? 0)) }}</a></div>
    </div>

    @if($errs)
    <details class="mb-3"><summary class="text-danger fw-semibold">{{ t('roll.errorCount', count($errs) >= 300 ? '300+' : count($errs)) }}</summary>
        <ul class="small text-danger mt-2">@foreach($errs as $e)<li>{{ $e }}</li>@endforeach</ul></details>
    @endif

    <details class="mb-3" @if($booth !== null) open @endif><summary class="fw-semibold">{{ t('roll.boothTable') }}</summary>
    <div class="table-responsive bg-white border rounded mt-2"><table class="table table-sm table-hover mb-0 text-end align-middle">
        <thead class="table-light"><tr><th class="text-start">{{ t('col.boothNo') }}</th><th>{{ t('roll.now') }}</th><th>{{ t('roll.inRoll') }}</th><th>{{ t('roll.diff') }}</th>
            <th>{{ t('roll.action.NEW') }}</th><th>{{ t('roll.action.DELETED') }}</th><th>{{ t('roll.shiftedIn') }}</th><th>{{ t('roll.shiftedOut') }}</th>
            <th>{{ t('roll.action.CHANGED') }}</th><th>{{ t('roll.action.NAMEMATCH') }}</th><th>{{ t('roll.action.RESTORED') }}</th></tr></thead>
        <tbody>
        @foreach($c['booths'] ?? [] as $b => $x)
            @php($d = $x['roll'] - $x['now'])
            <tr @class(['table-active' => $booth === (int) $b])><td class="text-start"><a href="?boothNo={{ $b }}">{{ $b }}</a></td><td>{{ number_format($x['now']) }}</td><td>{{ number_format($x['roll']) }}</td>
                <td class="{{ $d > 0 ? 'text-success' : ($d < 0 ? 'text-danger' : 'text-muted') }}">{{ $d > 0 ? '+' : '' }}{{ $d }}</td>
                <td>{{ $x['NEW'] ?: '' }}</td><td>{{ $x['DELETED'] ?: '' }}</td><td>{{ $x['in'] ?: '' }}</td><td>{{ $x['out'] ?: '' }}</td>
                <td>{{ $x['CHANGED'] ?: '' }}</td><td>{{ $x['NAMEMATCH'] ?: '' }}</td><td>{{ $x['RESTORED'] ?: '' }}</td></tr>
        @endforeach
        </tbody></table></div></details>

    <form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-2">
        <div><label class="form-label small mb-0">{{ t('roll.col.action') }}</label><select name="action" class="form-select form-select-sm">
            <option value="">{{ t('roll.allChanges') }}</option>
            @foreach(\App\Services\Voters\RollUpdateService::ACTIONS as $a)<option value="{{ $a }}" @selected($a === $action)>{{ t('roll.action.' . $a) }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select form-select-sm"><option value="">{{ t('common.all') }}</option>
            @foreach($u->boothList() as $b)<option value="{{ $b }}" @selected($b === $booth)>{{ $b }}</option>@endforeach</select></div>
        <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="survey" value="1" id="sv" @checked($survey)><label class="form-check-label small" for="sv">{{ t('roll.onlySurvey') }}</label></div>
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> {{ t('common.apply') }}</button>
    </form>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>{{ t('roll.col.action') }}</th><th>{{ t('col.boothNo') }}</th><th>{{ t('col.serialNo') }}</th><th>{{ t('col.epicNo') }}</th>
            <th>{{ t('col.name') }}</th><th>{{ t('roll.col.oldBooth') }} / {{ t('col.serialNo') }}</th><th>{{ t('roll.col.changes') }}</th><th>{{ t('roll.col.survey') }}</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr><td><span class="badge text-bg-{{ $cls[$r->action] }}">{{ t('roll.action.' . $r->action) }}</span></td>
                <td>{{ $r->booth_no }}</td><td>{{ $r->serial_no }}</td>
                <td>@if($r->voter_id)<a href="{{ url('/voters/' . $r->voter_id) }}">{{ $r->epic_no }}</a>@else{{ $r->epic_no }}@endif</td>
                <td>{{ $r->name }}</td>
                <td>@if($r->old_booth_no !== null && ($r->old_booth_no != $r->booth_no || $r->old_serial_no != $r->serial_no)){{ $r->old_booth_no }} / {{ $r->old_serial_no }}@endif</td>
                <td class="small">{{ \App\Services\Voters\RollUpdateService::changesText($r->changes) }}</td>
                <td>@if($r->has_survey)<i class="bi bi-clipboard-check text-success" title="{{ t('roll.col.survey') }}"></i>@endif</td></tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-3">{{ t('roll.noRows') }}</td></tr>
        @endforelse
        </tbody></table></div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection
