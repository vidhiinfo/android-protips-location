@extends('layouts.app')
@section('title', t($d['title']))
@section('content')
@php($listFields = array_filter($d['fields'], fn($f, $k) => $f[1] !== 'checkbox' || $k === 'current', ARRAY_FILTER_USE_BOTH))
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h4 class="me-auto mb-0"><i class="bi bi-database"></i> {{ t($d['title']) }}</h4>
        @if($d['type'] === 'candidates' || $d['filter'])
        <form method="get" class="d-flex gap-2">
            @if($d['type'] === 'candidates')
            <select name="electionId" class="form-select form-select-sm" data-autosubmit>
                @foreach($elections as $e)<option value="{{ $e->id }}" @selected($e->id == $electionId)>{{ $e->name }}</option>@endforeach</select>
            @endif
            @if($d['filter'])
            <select name="assemblyNo" class="form-select form-select-sm" data-autosubmit @disabled($me->scoped())>
                <option value="">{{ t('common.all') }}</option>
                @foreach($assemblies as $a)<option value="{{ $a->assembly_no }}" @selected($a->assembly_no == $filterValue)>{{ $a->assembly_no }} - {{ $a->name }}</option>@endforeach</select>
            @endif
        </form>
        @endif
        @if(can('MASTER_EDIT'))<a class="btn btn-primary btn-sm text-nowrap" href="{{ url('/masters/'.$d['type'].'/new') }}?assemblyNo={{ $filterValue }}&electionId={{ $electionId }}"><i class="bi bi-plus-lg"></i> {{ t('common.add') }}</a>@endif
    </div>
    <div class="table-responsive bg-white border rounded"><table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light"><tr>@if($d['type'] === 'parties')<th></th>@endif
            @foreach($listFields as $k => $f)<th>{{ $k === 'current' ? t('master.current') : t($f[0]) }}</th>@endforeach @if(can('MASTER_EDIT'))<th></th>@endif</tr></thead>
        <tbody>
        @forelse($items as $i)
            <tr>@if($d['type'] === 'parties')<td><span class="d-inline-block rounded-circle" style="width:14px;height:14px;background:{{ $i->color ?: '#6c757d' }}"></span></td>@endif
                @foreach($listFields as $k => $f)<td @if($f[1] === 'local') dir="auto" @endif>{{ \App\Http\Controllers\MasterController::display($i, $k, $d) }}</td>@endforeach
                @if(can('MASTER_EDIT'))<td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ url('/masters/'.$d['type'].'/'.$i->id.'/edit') }}" title="{{ t('common.edit') }}"><i class="bi bi-pencil"></i></a>
                    <form action="{{ url('/masters/'.$d['type'].'/'.$i->id.'/delete') }}" method="post" class="d-inline" data-confirm="{{ t('common.confirmDelete') }}">@csrf
                        <button class="btn btn-sm btn-outline-danger" title="{{ t('common.delete') }}"><i class="bi bi-trash"></i></button></form></td>@endif
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted py-4">{{ t('common.noRecords') }}</td></tr>
        @endforelse
        </tbody></table></div>
    @if(!empty($d['hint']))<p class="small text-muted mt-2">{{ t($d['hint']) }}</p>@endif
</div>
@endsection
