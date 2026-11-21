@extends('layouts.app')
@section('title', t('att.title'))
@section('content')
@php($osm = fn ($lat, $lng) => $lat !== null ? 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lng . '#map=18/' . $lat . '/' . $lng : null)
@php($hrs = fn ($r) => $r && $r->check_out ? round((strtotime($r->check_out) - strtotime($r->check_in)) / 3600, 1) : null)
<div class="container-fluid px-3" style="max-width:1200px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-calendar-check"></i> {{ t('att.title') }}</h4><div class="small text-muted">{{ t('att.intro') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @else<input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}">@endunless
            <div><label class="form-label small mb-0">{{ t('att.day') }}</label><input type="date" name="day" value="{{ $day }}" max="{{ date('Y-m-d') }}" class="form-control form-control-sm" data-autosubmit></div>
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/workers/tasks') }}?assemblyNo={{ $assemblyNo }}"><i class="bi bi-list-check"></i> {{ t('task.title') }}</a>
    </div>
    @php($present = $workers->filter(fn ($w) => $w->username && isset($rows[$w->username]))->count() + $others->count())
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat"><div class="small text-muted">{{ t('att.present') }}</div><div class="num">{{ $present }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="small text-muted">{{ t('att.absent') }}</div><div class="num">{{ $workers->filter(fn ($w) => $w->username && !isset($rows[$w->username]))->count() }}</div></div></div>
    </div>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>{{ t('worker.name') }}</th><th>{{ t('col.boothNo') }}</th><th>{{ t('att.in') }}</th><th>{{ t('att.out') }}</th><th class="text-end">{{ t('att.hours') }}</th><th class="text-end">{{ t('att.week') }}</th><th>{{ t('att.note') }}</th></tr></thead>
        <tbody>
        @foreach($workers as $w)
        @php($r = $w->username ? ($rows[$w->username] ?? null) : null)
        <tr>
            <td>{{ $w->name }} @unless($w->username)<span class="badge text-bg-light border">{{ t('task.noLogin') }}</span>@endunless</td>
            <td>{{ $w->booth_no ?: '-' }}</td>
            <td>@if($r){{ substr($r->check_in, 11, 5) }} @if($u = $osm($r->in_lat, $r->in_lng))<a href="{{ $u }}" target="_blank" rel="noopener" title="{{ t('att.where') }}"><i class="bi bi-geo-alt"></i></a>@endif
                @elseif($w->username)<span class="badge text-bg-secondary">{{ t('att.absent') }}</span>@else - @endif</td>
            <td>@if($r && $r->check_out){{ substr($r->check_out, 11, 5) }} @if($u = $osm($r->out_lat, $r->out_lng))<a href="{{ $u }}" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i></a>@endif @else - @endif</td>
            <td class="text-end">{{ $hrs($r) ?? '-' }}</td>
            <td class="text-end">{{ $w->username ? ($week[$w->username] ?? 0) . ' / 7' : '-' }}</td>
            <td class="small">{{ $r->note ?? '' }}</td>
        </tr>
        @endforeach
        @foreach($others as $u => $r)
        <tr><td>{{ $u }}</td><td>-</td><td>{{ substr($r->check_in, 11, 5) }}</td><td>{{ $r->check_out ? substr($r->check_out, 11, 5) : '-' }}</td>
            <td class="text-end">{{ $hrs($r) ?? '-' }}</td><td class="text-end">{{ ($week[$u] ?? 0) . ' / 7' }}</td><td class="small">{{ $r->note }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
