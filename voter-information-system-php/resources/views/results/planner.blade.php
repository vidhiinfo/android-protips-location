@extends('layouts.app')
@section('title', t('plan.title'))
@section('content')
@php
    $badge = ['swing' => 'warning text-dark', 'tough' => 'danger', 'track' => 'primary', 'safe' => 'success'];
    $n = fn ($v) => number_format((int) $v);
    $vl = fn ($b, $st) => url('/voters') . '?' . http_build_query(array_filter(['assemblyNo' => $assemblyNo, 'wardNo' => $wardNo, 'boothNo' => $b, 'sentiment' => $st], fn ($x) => $x !== null));
    $qs = fn (array $over) => url('/planner') . '?' . http_build_query(array_filter(array_merge($query, $over), fn ($x) => $x !== null && $x !== ''));
@endphp
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-bullseye"></i> {{ t('plan.title') }}</h4><div class="small text-muted">{{ t('plan.subtitle') }}</div></div>
        @if($plan)
        <div class="no-print d-flex gap-1">
            <a class="btn btn-sm btn-outline-success" href="{{ url('/planner/export/xlsx') . '?' . http_build_query($query) }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a class="btn btn-sm btn-outline-danger" href="{{ url('/planner/export/pdf') . '?' . http_build_query($query) }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <button type="button" class="btn btn-sm btn-secondary" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
        </div>
        @endif
    </div>

    <form method="get" class="card card-body py-2 mb-3 no-print">
        <div class="row g-2 align-items-end">
            @unless($me->scoped())
            <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label>
                <select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                    @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach
                </select></div>
            @endunless
            @if($wards)
            <div class="col-6 col-md-1"><label class="form-label small mb-0">{{ t('col.wardNo') }}</label>
                <select name="wardNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                    @foreach($wards as $w)<option value="{{ $w }}" @selected($w == $wardNo)>{{ $w }}</option>@endforeach
                </select></div>
            @endif
            <div class="col-6 col-md-2"><label class="form-label small mb-0">{{ t('plan.pastElection') }}</label>
                <select name="electionId" class="form-select form-select-sm" data-autosubmit>
                    @foreach($pasts as $e)<option value="{{ $e->id }}" @selected($past && $past->id === $e->id)>{{ $e->name }}</option>@endforeach
                    <option value="0" @selected(!$past)>{{ t('plan.noPast') }}</option>
                </select></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0">{{ t('plan.party') }}</label>
                @if($parties)
                <select name="party" class="form-select form-select-sm" data-autosubmit>
                    @foreach($parties as $short => $pname)<option value="{{ $short }}" @selected($plan && $plan['party'] === $short)>{{ $short }}</option>@endforeach
                </select>
                @else
                <input name="party" class="form-control form-control-sm" value="{{ request('party') }}" maxlength="20" placeholder="-">
                @endif
            </div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0" title="{{ t('plan.marginHelp') }}">{{ t('plan.margin') }} %</label>
                <input type="number" step="0.5" min="0" max="30" name="margin" class="form-control form-control-sm" value="{{ request('margin') }}" placeholder="{{ $plan['margin'] ?? '' }}"></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0" title="{{ t('plan.supporterHelp') }}">{{ t('plan.supporterTurnout') }} %</label>
                <input type="number" min="10" max="100" name="supporter" class="form-control form-control-sm" value="{{ request('supporter') }}" placeholder="{{ $plan['supporter_turnout'] ?? '' }}"></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0" title="{{ t('plan.turnoutHelp') }}">{{ t('plan.turnout') }} %</label>
                <input type="number" step="0.5" min="1" max="100" name="turnout" class="form-control form-control-sm" value="{{ request('turnout') }}" placeholder="{{ t('plan.auto') }}"></div>
            <div class="col-6 col-md-1"><label class="form-label small mb-0" title="{{ t('plan.targetHelp') }}">{{ t('plan.targetShare') }} %</label>
                <input type="number" step="0.5" min="1" max="95" name="target" class="form-control form-control-sm" value="{{ request('target') }}" placeholder="{{ isset($plan) ? number_format($plan['auto_target'], 1) : '' }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-calculator"></i> {{ t('plan.recalc') }}</button>
                <a class="btn btn-sm btn-link" href="{{ url('/planner') . '?' . http_build_query(array_filter(['assemblyNo' => $assemblyNo, 'wardNo' => $wardNo])) }}">{{ t('plan.resetWhatIf') }}</a></div>
        </div>
    </form>

    @if(!$plan)
        <div class="alert alert-info">{{ t('dash.pickAssembly') }}</div>
    @else
    @php($s = $plan['sum'])
    {{-- the seat in one sentence --}}
    <div class="alert {{ $plan['on_track'] ? 'alert-success' : 'alert-warning' }} py-2">
        @if($plan['past'])
            @php($p = $plan['past'])
            <div>{!! t('plan.lastTime', e($p['election']->name), '<b>' . e($p['party']) . '</b>', number_format($p['share'], 1), $p['won'] ? t('plan.won') : t('plan.lost'), $n(abs($p['margin'])), '<b>' . e($p['rival_party'] ?? '-') . '</b>', number_format($p['rival_share'], 1)) !!}</div>
        @else
            <div>{{ t('plan.noPastText', number_format($plan['target_share'], 1)) }}</div>
        @endif
        <div class="fw-semibold mt-1">{!! t('plan.toWin', '<b>' . $n($s['target']) . '</b>', number_format($plan['target_share'], 1), $n($s['expected']), number_format($plan['turnout'], 1)) !!}
            @if($plan['past']) {{ t('plan.swingText', sprintf('%+.1f', $plan['swing'])) }}@endif</div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat h-100"><div class="text-muted small">{{ t('plan.winNumber') }}</div><div class="num fs-4">{{ $n($s['target']) }}</div>
            <div class="small text-muted">{{ number_format($plan['target_share'], 1) }}% {{ t('plan.ofExpected', $n($s['expected'])) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat h-100"><div class="text-muted small">{{ t('plan.projected') }}</div><div class="num fs-4">{{ $n($s['projected']) }}</div>
            <div class="small text-muted">{{ t('plan.fromSupporters', $n($s['S'])) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat h-100 {{ $plan['seat_gap'] ? 'border-warning' : 'border-success' }}"><div class="text-muted small">{{ t('plan.stillNeeded') }}</div>
            <div class="num fs-4 {{ $plan['seat_gap'] ? 'text-danger' : 'text-success' }}">{{ $plan['seat_gap'] ? $n($plan['seat_gap']) : t('plan.onTrack') }}</div>
            <div class="small text-muted">{{ t('plan.reachablePool', $n($s['reachable'])) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat h-100"><div class="text-muted small">{{ t('plan.surveyDone') }}</div><div class="num fs-4">{{ number_format($plan['surveyed_pct'], 0) }}%</div>
            <div class="small text-muted">{{ t('plan.notSurveyedN', $n($s['U'])) }}</div></div></div>
    </div>

    @php($pct = $s['target'] ? min(100, 100 * $s['projected'] / $s['target']) : 0)
    <div class="mb-1 small d-flex"><span class="me-auto">{{ t('plan.progress') }}</span><span>{{ $n($s['projected']) }} / {{ $n($s['target']) }} ({{ number_format($pct, 0) }}%)</span></div>
    <div class="progress mb-3" style="height:14px" role="progressbar" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct >= 85 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $pct }}%"></div></div>

    <ul class="nav nav-pills mb-2 no-print small">
        <li class="nav-item"><a class="nav-link py-1 {{ $status ? '' : 'active' }}" href="{{ $qs(['status' => null]) }}">{{ t('common.all') }} ({{ count($plan['rows']) }})</a></li>
        @foreach(\App\Services\Results\PlannerService::STATUSES as $st)
        <li class="nav-item"><a class="nav-link py-1 {{ $status === $st ? 'active' : '' }}" href="{{ $qs(['status' => $st]) }}">{{ t('plan.st.' . $st) }} ({{ $plan['counts'][$st] }})</a></li>
        @endforeach
    </ul>
    <p class="small text-muted mb-2">{{ $status ? t('plan.stHelp.' . $status) : t('plan.orderHelp') }}</p>

    <div class="table-responsive mb-3">
    <table class="table table-sm table-hover align-middle small">
        <thead class="table-light"><tr>
            <th>{{ t('col.boothNo') }}</th><th>{{ t('plan.status') }}</th><th class="text-end">{{ t('plan.electors') }}</th><th class="text-end">{{ t('plan.expected') }}</th>
            <th class="text-end">{{ t('plan.pastMargin') }}</th><th class="text-end">{{ t('plan.target') }}</th><th style="min-width:9rem">{{ t('plan.survey') }}</th>
            <th class="text-end">{{ t('plan.projected') }}</th><th style="min-width:7rem">{{ t('plan.progress') }}</th><th class="text-end">{{ t('plan.gap') }}</th><th>{{ t('plan.action') }}</th>
        </tr></thead>
        <tbody>
        @foreach($plan['rows'] as $b)
            @continue($status && $b['status'] !== $status)
            <tr>
                <td><a href="{{ $vl($b['booth_no'], null) }}" class="fw-semibold">{{ $b['booth_no'] }}</a><div class="text-muted text-truncate" style="max-width:12rem" title="{{ $b['booth_name'] }}">{{ preg_replace('/^\s*' . $b['booth_no'] . '\s*[-.:]\s*/u', '', $b['booth_name']) }}</div></td>
                <td><span class="badge bg-{{ $badge[$b['status']] }}">{{ t('plan.st.' . $b['status']) }}</span></td>
                <td class="text-end">{{ $n($b['electors']) }}</td>
                <td class="text-end">{{ $n($b['expected']) }}<div class="text-muted">{{ number_format($b['turnout'], 0) }}%</div></td>
                <td class="text-end">@if($b['past_margin'] === null)<span class="text-muted">-</span>@else<span class="{{ $b['past_margin'] >= 0 ? 'text-success' : 'text-danger' }}">{{ sprintf('%+d', $b['past_margin']) }}</span>
                    <div class="text-muted">{{ number_format($b['past']['share'], 0) }}%</div>@endif</td>
                <td class="text-end fw-semibold">{{ $n($b['target']) }}<div class="text-muted fw-normal">{{ number_format($b['target_share'], 0) }}%</div></td>
                <td>
                    @php($E = max(1, $b['electors']))
                    <div class="progress" style="height:8px" title="{{ t('plan.supporters') }} {{ $b['S'] }} · {{ t('plan.neutral') }} {{ $b['N'] }} · {{ t('plan.opposition') }} {{ $b['O'] }} · {{ t('plan.notSurveyed') }} {{ $b['U'] }}">
                        <div class="progress-bar bg-success" style="width:{{ 100 * $b['S'] / $E }}%"></div><div class="progress-bar bg-warning" style="width:{{ 100 * $b['N'] / $E }}%"></div>
                        <div class="progress-bar bg-danger" style="width:{{ 100 * $b['O'] / $E }}%"></div></div>
                    <div class="text-nowrap"><a class="text-success" href="{{ $vl($b['booth_no'], 'S') }}">{{ $b['S'] }}</a> · <a class="text-warning-emphasis" href="{{ $vl($b['booth_no'], 'N') }}">{{ $b['N'] }}</a>
                        · <a class="text-danger" href="{{ $vl($b['booth_no'], 'O') }}">{{ $b['O'] }}</a> · <a class="text-muted" href="{{ $vl($b['booth_no'], 'U') }}">{{ $b['U'] }}</a></div>
                </td>
                <td class="text-end">{{ $n($b['projected']) }}</td>
                <td><div class="progress" style="height:8px"><div class="progress-bar {{ $b['progress'] >= 100 ? 'bg-success' : ($b['progress'] >= 85 ? 'bg-warning' : 'bg-danger') }}" style="width:{{ $b['progress'] }}%"></div></div>
                    <div class="text-muted">{{ number_format($b['progress'], 0) }}%</div></td>
                <td class="text-end fw-semibold {{ $b['gap'] ? 'text-danger' : 'text-success' }}">{{ $b['gap'] ? $n($b['gap']) : '✓' }}</td>
                <td>{{ \App\Http\Controllers\PlannerController::action($b) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot class="table-light fw-semibold"><tr>
            <td colspan="2">{{ t('report.total') }}</td><td class="text-end">{{ $n($s['electors']) }}</td><td class="text-end">{{ $n($s['expected']) }}</td>
            <td class="text-end">{{ $plan['past'] ? sprintf('%+d', $plan['past']['margin']) : '' }}</td><td class="text-end">{{ $n($s['target']) }}</td>
            <td>{{ $s['S'] }} · {{ $s['N'] }} · {{ $s['O'] }} · {{ $s['U'] }}</td><td class="text-end">{{ $n($s['projected']) }}</td><td>{{ number_format($pct, 0) }}%</td>
            <td class="text-end">{{ $plan['seat_gap'] ? $n($plan['seat_gap']) : '✓' }}</td><td></td>
        </tr></tfoot>
    </table>
    </div>
    <details class="mb-3 small"><summary class="fw-semibold">{{ t('plan.howTitle') }}</summary><p class="mt-2 mb-0 text-muted" style="white-space:pre-line">{{ t('plan.how') }}</p></details>
    @endif

    @if(can('RESULT_ENTRY') && $assemblyNo)
    <div class="card mb-4 no-print"><div class="card-body">
        <h6><i class="bi bi-upload"></i> {{ t('form20.title') }}</h6>
        <p class="small text-muted mb-2">{{ t('form20.help') }} <a href="{{ url('/planner/form20-template') . '?' . http_build_query(array_filter(['assemblyNo' => $assemblyNo, 'electionId' => $past?->id])) }}">{{ t('form20.template') }}</a></p>
        <form method="post" action="{{ url('/planner/form20') }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf
            <input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}">
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('form20.election') }}</label>
                <select name="electionId" class="form-select form-select-sm" data-form20-election>
                    @foreach($elections as $e)<option value="{{ $e->id }}" @selected($past && $past->id === $e->id)>{{ $e->name }}</option>@endforeach
                    <option value="new" @selected($elections->isEmpty())>+ {{ t('form20.newElection') }}</option>
                </select></div>
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('form20.newName') }}</label><input name="newName" class="form-control form-control-sm" maxlength="150" placeholder="{{ t('form20.newNamePh') }}"></div>
            <div class="col-md-1"><label class="form-label small mb-0">{{ t('form20.year') }}</label><input type="number" name="newYear" class="form-control form-control-sm" min="1951" max="2100"></div>
            <div class="col-md-3"><label class="form-label small mb-0">{{ t('form20.file') }}</label><input type="file" name="file" accept=".xlsx,.csv" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i> {{ t('form20.upload') }}</button></div>
        </form>
    </div></div>
    @endif
</div>
@endsection
