@extends('layouts.app')
@section('title', t('roll.title'))
@section('content')
@php($stCls = ['PREVIEW' => 'text-bg-warning', 'APPLIED' => 'text-bg-success', 'UNDONE' => 'text-bg-secondary', 'DISCARDED' => 'text-bg-light border'])
<div class="container" style="max-width:1100px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-arrow-repeat"></i> {{ t('roll.title') }}</h4>
    <p class="text-muted small mb-3">{{ t('roll.intro') }}</p>
    <div class="row g-3 mb-3">
        <div class="col-lg-7"><div class="card h-100"><div class="card-body">
            <h6>{{ t('roll.how') }}</h6>
            <ol class="small mb-0">
                <li>{{ t('roll.step1') }}</li><li>{{ t('roll.step2') }}</li><li>{{ t('roll.step3') }}</li><li>{{ t('roll.step4') }}</li>
            </ol>
            <div class="alert alert-info small py-2 mt-3 mb-0"><i class="bi bi-shield-check"></i> {{ t('roll.keeps') }}</div>
        </div></div></div>
        <div class="col-lg-5"><div class="card h-100"><div class="card-body">
            <h6>{{ t('roll.upload') }}</h6>
            @if(!empty($error))<div class="alert alert-danger py-2 small">{{ $error }}</div>@endif
            <form action="{{ url('/roll-update') }}" method="post" enctype="multipart/form-data">@csrf
                <label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label>
                <select name="assemblyNo" class="form-select mb-2" required @disabled($me->scoped())>
                    <option value="">--</option>
                    @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $defaultAssembly)>{{ $no }}{{ $name ? ' - ' . $name : '' }}</option>@endforeach
                </select>
                <label class="form-label small mb-0">{{ t('roll.file') }}</label>
                <input type="file" name="file" accept=".xlsx" class="form-control mb-1" required>
                <div class="form-text mb-2">{{ t('roll.fileHelp') }}</div>
                <button class="btn btn-primary w-100" data-busy="{{ t('roll.comparing') }}"><i class="bi bi-search"></i> {{ t('roll.compare') }}</button>
            </form>
        </div></div></div>
    </div>
    <h6>{{ t('roll.history') }}</h6>
    <div class="table-responsive bg-white border rounded"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>{{ t('col.assemblyNo') }}</th><th>{{ t('roll.file') }}</th><th>{{ t('roll.status') }}</th>
            <th class="text-end">{{ t('roll.action.NEW') }}</th><th class="text-end">{{ t('roll.action.DELETED') }}</th><th class="text-end">{{ t('roll.action.SHIFTED') }}</th>
            <th class="text-end">{{ t('roll.action.CHANGED') }}</th><th>{{ t('roll.when') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($list as $u)
            @php($a = $u->countsData()['actions'] ?? [])
            <tr><td>{{ $u->id }}</td><td>{{ $u->assembly_no }}</td><td class="text-break">{{ $u->file_name }}</td>
                <td><span class="badge {{ $stCls[$u->status] ?? 'text-bg-light' }}">{{ t('roll.status.' . $u->status) }}</span></td>
                <td class="text-end">{{ number_format($a['NEW'] ?? 0) }}</td><td class="text-end">{{ number_format($a['DELETED'] ?? 0) }}</td>
                <td class="text-end">{{ number_format($a['SHIFTED'] ?? 0) }}</td><td class="text-end">{{ number_format(($a['CHANGED'] ?? 0) + ($a['NAMEMATCH'] ?? 0)) }}</td>
                <td class="small">{{ $u->created_at?->format('d-m-Y H:i') }}<div class="text-muted">{{ $u->created_by }}</div></td>
                <td>@if($u->status !== 'DISCARDED')<a class="btn btn-sm btn-outline-primary" href="{{ url('/roll-update/' . $u->id) }}">{{ t('roll.open') }}</a>@endif</td></tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted py-3">{{ t('roll.none') }}</td></tr>
        @endforelse
        </tbody></table></div>
</div>
@endsection
