@extends('layouts.app')
@section('title', t('state.title'))
@push('head')<meta http-equiv="refresh" content="{{ max(60, (int) config('voters.state.refresh_seconds', 180)) }}">@endpush
@section('content')
@php($T = $sum['total'])
@php($pct = fn ($a, $b) => \App\Services\State\StateSummaryService::pct((int) $a, (int) $b))
@php($rows = $division ? $division['assemblies'] : $sum['divisions'])
<div class="container-fluid px-3" style="max-width:1500px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="me-auto">
            <h4 class="mb-0"><i class="bi bi-map"></i> {{ t('state.title') }}@if($division) <span class="text-muted">› {{ $division['name'] }}</span>@endif</h4>
            <div class="small text-muted">@if($sum['election']){{ $sum['election']['name'] }} · @endif{{ t('state.online', $sum['online'], $sum['count']) }} · {{ t('state.autoRefresh') }}</div>
        </div>
        @if($division)<a class="btn btn-sm btn-outline-secondary" href="{{ url('/state') }}"><i class="bi bi-arrow-left"></i> {{ t('state.allDivisions') }}</a>
            <a class="btn btn-sm btn-outline-primary" href="{{ $division['url'] }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ t('state.openDivision') }}</a>@endif
        <a class="btn btn-sm btn-outline-secondary" href="?{{ http_build_query(array_filter(['division' => $division['name'] ?? null, 'refresh' => 1])) }}"><i class="bi bi-arrow-clockwise"></i> {{ t('state.refresh') }}</a>
        <a class="btn btn-sm btn-success" href="{{ url('/state/export') }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    </div>

    @php($K = $division ?: $T)
    <div class="row g-2 mb-3">
        @foreach([
            ['state.voters', number_format($K['voters']), t('gender.MALE') . ' ' . number_format($K['male']) . ' · ' . t('gender.FEMALE') . ' ' . number_format($K['female'])],
            ['state.assembliesBooths', number_format($division ? count($division['assemblies']) : $T['assemblies']) . ' / ' . number_format($K['booths']), ''],
            ['state.surveyed', $pct($K['surveyed'], $K['voters']), number_format($K['surveyed']) . ' · ' . t('state.mobile') . ' ' . $pct($K['mobile'], $K['voters'])],
            ['state.support', $pct($K['S'], $K['surveyed']), t('state.neutral') . ' ' . $pct($K['N'], $K['surveyed']) . ' · ' . t('state.opposition') . ' ' . $pct($K['O'], $K['surveyed'])],
            ['state.workers', number_format($K['workers']), t('state.panna') . ' ' . number_format($K['panna'])],
            ['state.grievances', number_format($K['grievances']), ''],
            ['state.turnout', $pct($K['voted'], $K['voters']), t('state.supportersVoted') . ' ' . $pct($K['supportersVoted'], $K['S'])],
        ] as [$label, $value, $sub])
        <div class="col-6 col-md-4 col-xl"><div class="stat h-100"><div class="text-muted small">{{ t($label) }}</div><div class="num">{{ $value }}</div>
            @if($sub)<div class="small text-muted">{{ $sub }}</div>@endif</div></div>
        @endforeach
    </div>

    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-hover align-middle mb-0 text-end">
        <thead class="table-light"><tr>
            <th class="text-start">{{ $division ? t('col.assemblyName') : t('state.division') }}</th>
            @unless($division)<th class="text-center">{{ t('state.status') }}</th><th>{{ t('state.assemblies') }}</th>@endunless
            <th>{{ t('state.voters') }}</th><th>{{ t('state.booths') }}</th><th>{{ t('state.mobile') }}</th><th>{{ t('state.surveyed') }}</th>
            <th>{{ t('state.support') }}</th><th>{{ t('state.neutral') }}</th><th>{{ t('state.opposition') }}</th>
            <th>{{ t('state.workers') }}</th><th>{{ t('state.panna') }}</th><th>{{ t('state.grievances') }}</th><th>{{ t('state.turnout') }}</th><th>{{ t('state.supportersPending') }}</th>
            @unless($division)<th class="text-start">{{ t('state.updated') }}</th>@endunless
        </tr></thead>
        <tbody>
        @forelse($rows as $x)
            <tr>
                <td class="text-start">@if($division){{ $x['assembly_no'] }} - {{ $x['name'] }}@else<a href="?division={{ urlencode($x['name']) }}" class="fw-semibold">{{ $x['name'] }}</a>@endif</td>
                @unless($division)
                <td class="text-center">@if($x['ok'])<span class="badge text-bg-success">{{ t('state.ok') }}</span>@else<span class="badge text-bg-danger" title="{{ $x['error'] }}">{{ t('state.offline') }}</span>@endif</td>
                <td>{{ count($x['assemblies']) }}</td>@endunless
                <td>{{ number_format($x['voters']) }}</td><td>{{ number_format($x['booths']) }}</td><td>{{ $pct($x['mobile'], $x['voters']) }}</td><td>{{ $pct($x['surveyed'], $x['voters']) }}</td>
                <td class="text-success">{{ $pct($x['S'], $x['surveyed']) }}</td><td>{{ $pct($x['N'], $x['surveyed']) }}</td><td class="text-danger">{{ $pct($x['O'], $x['surveyed']) }}</td>
                <td>{{ number_format($x['workers']) }}</td><td>{{ number_format($x['panna']) }}</td><td>{{ number_format($x['grievances']) }}</td>
                <td class="fw-semibold">{{ $pct($x['voted'], $x['voters']) }}</td><td>{{ number_format(max(0, $x['S'] - $x['supportersVoted'])) }}</td>
                @unless($division)<td class="text-start small text-muted">{{ $x['lastOkAt'] ? \Illuminate\Support\Carbon::parse($x['lastOkAt'])->format('d-m H:i') : '-' }}
                    @if(!$x['ok'] && $x['hasData'])<div class="text-danger">{{ t('state.lastKnown') }}</div>@endif</td>@endunless
            </tr>
        @empty
            <tr><td colspan="16" class="text-center text-muted py-3">{{ t('state.noData') }}</td></tr>
        @endforelse
        </tbody>
        @if($rows)
        <tfoot class="table-light fw-semibold"><tr>
            <td class="text-start">{{ t('state.total') }}</td>
            @unless($division)<td></td><td>{{ $T['assemblies'] }}</td>@endunless
            <td>{{ number_format($K['voters']) }}</td><td>{{ number_format($K['booths']) }}</td><td>{{ $pct($K['mobile'], $K['voters']) }}</td><td>{{ $pct($K['surveyed'], $K['voters']) }}</td>
            <td>{{ $pct($K['S'], $K['surveyed']) }}</td><td>{{ $pct($K['N'], $K['surveyed']) }}</td><td>{{ $pct($K['O'], $K['surveyed']) }}</td>
            <td>{{ number_format($K['workers']) }}</td><td>{{ number_format($K['panna']) }}</td><td>{{ number_format($K['grievances']) }}</td>
            <td>{{ $pct($K['voted'], $K['voters']) }}</td><td>{{ number_format(max(0, $K['S'] - $K['supportersVoted'])) }}</td>@unless($division)<td></td>@endunless
        </tr></tfoot>
        @endif
    </table></div>
    <p class="small text-muted mt-2"><i class="bi bi-shield-lock"></i> {{ t('state.privacy') }}</p>
</div>
@endsection
