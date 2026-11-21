@extends('layouts.app')
@section('title', t('dup.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-people"></i> {{ t('dup.title') }}</h4><div class="small text-muted">{{ t('dup.intro') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @endunless
            <div><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
            <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="anyAge" value="1" id="anyAge" @checked(!$sameAge) data-autosubmit>
                <label class="form-check-label small" for="anyAge">{{ t('dup.anyAge') }}</label></div>
        </form>
    </div>
    <div class="alert alert-warning small py-2">{{ t('dup.warn', implode(', ', array_map(fn ($f) => t('col.' . lcfirst(str_replace('_', '', ucwords($f, '_')))), array_slice($fields, 0, 8)))) }}</div>
    <div class="small text-muted mb-2">{{ t('dup.found', $total) }}</div>

    @foreach($groups as $gi => $g)
    <form method="post" action="{{ url('/voters/duplicates/merge') }}" class="card mb-3" data-confirm="{{ t('dup.confirm') }}">@csrf
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>{{ t('dup.keep') }}</th><th>{{ t('dup.mergeIt') }}</th><th>{{ t('col.epicNo') }}</th><th>{{ t('col.name') }}</th><th>{{ t('col.relativeName') }}</th>
                <th>{{ t('col.age') }}</th><th>{{ t('col.gender') }}</th><th>{{ t('col.boothNo') }} / {{ t('col.serialNo') }}</th><th>{{ t('col.houseNo') }}</th><th>{{ t('col.mobile') }}</th><th>{{ t('col.caste') }}</th><th>{{ t('col.politicalStatus') }}</th></tr></thead>
            <tbody>
            @foreach($g as $i => $v)
            <tr>
                <td><input class="form-check-input" type="radio" name="keep" value="{{ $v->id }}" @checked($i === 0) data-dup-keep></td>
                <td><input class="form-check-input" type="checkbox" name="drop[]" value="{{ $v->id }}" @checked($i > 0) data-dup-drop></td>
                <td class="text-nowrap"><a href="{{ url('/voters/' . $v->id) }}" target="_blank">{{ $v->epic_no }}</a></td>
                <td>{{ $v->name }}</td><td>{{ $v->relative_name }}</td><td>{{ $v->age }}</td><td>{{ $v->genderLabel() }}</td>
                <td>{{ $v->booth_no }} / {{ $v->serial_no }}</td><td>{{ $v->house_no }}</td><td>{{ $v->mobile }}</td><td>{{ $v->caste }}</td><td>{{ $v->political_status }}</td>
            </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer py-1 text-end"><button class="btn btn-sm btn-primary"><i class="bi bi-intersect"></i> {{ t('dup.mergeBtn') }}</button></div>
    </form>
    @endforeach
    @if($pages > 1)
    <nav class="mb-3"><ul class="pagination pagination-sm">
        @for($p = 1; $p <= min($pages, 40); $p++)<li class="page-item {{ $p === $page ? 'active' : '' }}"><a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a></li>@endfor
    </ul></nav>
    @endif

    <h6 class="mt-4">{{ t('dup.recent') }}</h6>
    <div class="table-responsive bg-white border rounded mb-4"><table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>{{ t('dup.when') }}</th><th>{{ t('dup.kept') }}</th><th>{{ t('dup.removed') }}</th><th>{{ t('dup.by') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($merges as $m)
        <tr class="{{ $m->undone_at ? 'text-muted' : '' }}"><td class="small">{{ $m->at }}</td>
            <td>{{ $names[$m->keep_id]->name ?? '#' . $m->keep_id }} <span class="small text-muted">{{ $names[$m->keep_id]->epic_no ?? '' }}</span></td>
            <td>{{ $names[$m->drop_id]->name ?? '#' . $m->drop_id }} <span class="small text-muted">{{ $names[$m->drop_id]->epic_no ?? '' }}</span></td>
            <td class="small">{{ $m->by_user }}</td>
            <td class="text-end">@if($m->undone_at)<span class="badge text-bg-light border">{{ t('dup.undoneBadge') }}</span>
                @else<form method="post" action="{{ url('/voters/duplicates/' . $m->id . '/undo') }}" data-confirm="{{ t('dup.undoConfirm') }}">@csrf<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> {{ t('dup.undo') }}</button></form>@endif</td></tr>
        @empty
        <tr><td colspan="5" class="text-muted text-center py-3">{{ t('list.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
