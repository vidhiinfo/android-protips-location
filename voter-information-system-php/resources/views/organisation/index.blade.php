@extends('layouts.app')
@section('title', t('org.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-diagram-3"></i> {{ t('org.title') }}</h4><div class="small text-muted">{{ t('org.intro') }}</div></div>
        <a class="btn btn-sm btn-primary" href="{{ url('/workers/new') }}"><i class="bi bi-plus-lg"></i> {{ t('worker.add') }}</a>
    </div>
    @unless($me->scoped())
    <div class="card mb-3"><div class="card-body py-2">
        <span class="fw-semibold"><i class="bi bi-bank2"></i> {{ t('worker.role.LS_INCHARGE') }}:</span>
        @forelse($heads as $h)<a href="{{ url('/workers/' . $h->id) }}" class="ms-1">{{ $h->name }}</a>@if($h->mobile) <span class="small text-muted">{{ $h->mobile }}</span>@endif{{ !$loop->last ? ',' : '' }}
        @empty<span class="text-danger small ms-1"><i class="bi bi-exclamation-circle"></i> {{ t('org.missing') }}</span>@endforelse
    </div></div>
    @endunless
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>{{ t('col.assemblyNo') }}</th><th>{{ t('worker.role.AC_INCHARGE') }}</th><th class="text-end">{{ t('ls.mandals') }}</th>
            <th class="text-end">{{ t('ls.booths') }}</th><th class="text-end">{{ t('org.withPresident') }}</th><th class="text-end">{{ t('org.withPanna') }}</th>
            <th class="text-end">{{ t('ls.panna') }}</th><th class="text-end">{{ t('ls.workers') }}</th><th>{{ t('org.gaps') }}</th><th class="text-end">{{ t('org.tasksDone') }}</th></tr></thead>
        <tbody>@foreach($assemblies as $no => $name) @php($s = $summary[$no])
            <tr>
                <td><a class="fw-semibold" href="{{ url('/organisation/' . $no) }}">{{ $no }} - {{ $name }}</a></td>
                <td>@forelse($s['incharge'] as $h)<a href="{{ url('/workers/' . $h->id) }}">{{ $h->name }}</a>{{ !$loop->last ? ', ' : '' }}@empty<span class="text-danger small"><i class="bi bi-exclamation-circle"></i> {{ t('org.missing') }}</span>@endforelse</td>
                <td class="text-end">{{ $s['mandals'] }}</td><td class="text-end">{{ $s['booths'] }}</td>
                <td class="text-end">{{ $s['withPresident'] }}</td><td class="text-end">{{ $s['withPanna'] }}</td>
                <td class="text-end">{{ $s['voters'] ? round(100 * $s['pannaVoters'] / $s['voters'], 1) . '%' : '-' }}</td><td class="text-end">{{ $s['workers'] }}</td>
                <td class="small">
                    @if($s['booths'] - $s['withPresident'] > 0)<span class="badge text-bg-danger-subtle text-danger-emphasis me-1">{{ t('org.gapPresident', $s['booths'] - $s['withPresident']) }}</span>@endif
                    @if($s['mandalsNoHead'])<span class="badge text-bg-warning-subtle text-warning-emphasis me-1">{{ t('org.gapMandalHead', $s['mandalsNoHead']) }}</span>@endif
                    @if($s['booths'] && $s['boothsNoMandal'])<span class="badge text-bg-secondary-subtle text-secondary-emphasis">{{ t('org.gapNoMandal', $s['boothsNoMandal']) }}</span>@endif
                </td>
                <td class="text-end">{{ $s['tasks']['total'] ? $s['tasks']['done'] . '/' . $s['tasks']['total'] : '-' }}</td>
            </tr>
        @endforeach</tbody>
    </table></div></div>
    <p class="small text-muted mt-2">{{ t('org.chainNote') }}</p>
</div>
@endsection
