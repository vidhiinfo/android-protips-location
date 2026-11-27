@extends('layouts.app')
@section('title', t('ls.title'))
@section('content')
@php($T = $data['total'])
@php($flagCls = ['green' => 'success', 'amber' => 'warning', 'red' => 'danger', 'none' => 'secondary'])
<div class="container-fluid px-3" style="max-width:1400px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto">
            <h4 class="mb-0"><i class="bi bi-bank2"></i> {{ t('ls.title') }}@if(config('voters.branding.constituency')) <span class="text-muted fw-normal">· {{ config('voters.branding.constituency') }}</span>@endif</h4>
            <div class="small text-muted">{{ t('ls.intro') }}</div>
        </div>
        @if(can('WORKER_MANAGE'))<a class="btn btn-sm btn-outline-primary" href="{{ url('/organisation') }}"><i class="bi bi-diagram-3"></i> {{ t('org.title') }}</a>@endif
        <button type="button" class="btn btn-sm btn-secondary" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
    </div>
    @if($heads->isNotEmpty())
    <div class="small mb-2"><i class="bi bi-person-badge"></i> {{ role_label('LS_INCHARGE') }}: @foreach($heads as $h)<b>{{ $h->name }}</b>@if($h->mobile) ({{ $h->mobile }})@endif{{ !$loop->last ? ', ' : '' }}@endforeach</div>
    @endif
    @if($T)
    <div class="row g-2 mb-3">
        @foreach([['ls.segments', $T['segments'] . ($T['withData'] < $T['segments'] ? ' (' . t('ls.withData', $T['withData']) . ')' : '')], ['dash.voters', number_format($T['voters'])], ['ls.booths', number_format($T['booths'])],
                  ['ls.survey', $T['surveyPct'] . '%'], ['ls.president', $T['presidentPct'] . '%'], ['ls.panna', $T['pannaPct'] . '%'], ['ls.workers', number_format($T['workers'])], ['ls.griev', $T['griev']]] as [$l, $v])
        <div class="col-6 col-md-3 col-xl"><div class="stat h-100"><div class="text-muted small">{{ t($l) }}</div><div class="num fs-5">{{ $v }}</div></div></div>
        @endforeach
    </div>
    @endif
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>{{ t('ls.segment') }}</th><th>{{ t('ls.flag') }}</th><th class="text-end">{{ t('dash.voters') }}</th><th class="text-end">{{ t('ls.booths') }}</th>
            <th class="text-end">{{ t('an.womenPct') }}</th><th class="text-end">{{ t('an.age18') }}</th><th class="text-end">{{ t('report.mobilePct') }}</th>
            <th class="text-end">{{ t('ls.survey') }}</th><th class="text-end">{{ t('ls.support') }}</th><th class="text-end">{{ t('ls.president') }}</th>
            <th class="text-end">{{ t('ls.panna') }}</th><th class="text-end">{{ t('ls.mandals') }}</th><th>{{ role_label('AC_INCHARGE') }}</th><th class="text-end">{{ t('ls.griev') }}</th>
        </tr></thead>
        <tbody>@foreach($data['rows'] as $r)
            <tr>
                <td><a href="{{ url('/analytics') }}?assemblyNo={{ $r['no'] }}" class="fw-semibold">{{ $r['no'] }} - {{ $r['name'] }}</a></td>
                <td><span class="badge text-bg-{{ $flagCls[$r['flag']] }}">{{ t('ls.flag.' . $r['flag']) }}</span></td>
                <td class="text-end">@if($r['voters'])<a href="{{ url('/voters') }}?assemblyNo={{ $r['no'] }}">{{ number_format($r['voters']) }}</a>@else<span class="text-muted">{{ t('ls.noRoll') }}</span>@endif</td>
                <td class="text-end">{{ $r['booths'] ?: '-' }}</td>
                <td class="text-end">{{ $r['voters'] ? $r['womenPct'] : '-' }}</td><td class="text-end">{{ $r['voters'] ? number_format($r['first']) : '-' }}</td>
                <td class="text-end">{{ $r['voters'] ? $r['mobilePct'] : '-' }}</td><td class="text-end">{{ $r['voters'] ? $r['surveyPct'] : '-' }}</td>
                <td class="text-end">{{ $r['voters'] ? $r['supportPct'] : '-' }}</td>
                <td class="text-end">@if($r['booths'])<a href="{{ url('/organisation/' . $r['no']) }}">{{ $r['presidentPct'] }}</a> <span class="small text-muted">({{ $r['withPresident'] }}/{{ $r['booths'] }})</span>@else - @endif</td>
                <td class="text-end">{{ $r['voters'] ? $r['pannaPct'] : '-' }}</td><td class="text-end">{{ $r['mandals'] }}</td>
                <td>@forelse($r['incharge'] as $h){{ $h->name }}{{ !$loop->last ? ', ' : '' }}@empty<span class="text-danger small"><i class="bi bi-exclamation-circle"></i> {{ t('org.missing') }}</span>@endforelse</td>
                <td class="text-end">{{ $r['griev'] }}</td>
            </tr>
        @endforeach</tbody>
        @if($T)<tfoot class="table-secondary fw-semibold"><tr><td>{{ t('report.total') }}</td><td>@foreach(['green', 'amber', 'red'] as $f)@if(($T['flags'][$f] ?? 0))<span class="badge text-bg-{{ $flagCls[$f] }} me-1">{{ $T['flags'][$f] }}</span>@endif @endforeach</td>
            <td class="text-end">{{ number_format($T['voters']) }}</td><td class="text-end">{{ number_format($T['booths']) }}</td><td class="text-end">{{ $T['womenPct'] }}</td><td class="text-end">{{ number_format($T['first']) }}</td>
            <td class="text-end">{{ $T['mobilePct'] }}</td><td class="text-end">{{ $T['surveyPct'] }}</td><td class="text-end">{{ $T['supportPct'] }}</td><td class="text-end">{{ $T['presidentPct'] }}</td>
            <td class="text-end">{{ $T['pannaPct'] }}</td><td class="text-end">{{ $T['mandals'] }}</td><td></td><td class="text-end">{{ $T['griev'] }}</td></tr></tfoot>@endif
    </table></div></div>
    <p class="small text-muted mt-2">{{ t('ls.note', \App\Services\Org\LokSabhaService::GREEN_SURVEY, \App\Services\Org\LokSabhaService::GREEN_PRESIDENT, \App\Services\Org\LokSabhaService::RED_SURVEY, \App\Services\Org\LokSabhaService::RED_PRESIDENT) }}</p>
</div>
@endsection
