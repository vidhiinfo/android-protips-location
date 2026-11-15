@extends('layouts.app')
@section('title', t('nav.voterList'))
@section('content')
<div class="container-fluid px-3">
    @include('partials.flash')
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('list.totalVoters') }}</div><div class="num">{{ $total }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('gender.MALE') }}</div><div class="num">{{ $genderCounts['MALE'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('gender.FEMALE') }}</div><div class="num">{{ $genderCounts['FEMALE'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('list.matching') }}</div><div class="num text-primary">{{ $page->total() }}</div></div></div>
    </div>
    <form method="get" action="{{ url('/voters') }}" class="card filter-card mb-3"><div class="card-body pb-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-6"><label class="form-label">{{ t('list.quickSearch') }}</label>
                <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input name="q" value="{{ $f->get('q') }}" class="form-control" dir="auto" placeholder="{{ t('list.quickSearchHint') }}"></div></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.assemblyNo') }}</label>
                <select name="assemblyNo" class="form-select" @disabled($me->scoped())>
                    <option value="">{{ t('common.all') }}</option>
                    @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $f->get('assemblyNo'))>{{ $no }}{{ $name ? ' - '.$name : '' }}</option>@endforeach
                </select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.boothNo') }}</label>
                <select name="boothNo" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $f->get('boothNo'))>{{ $b }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> {{ t('common.search') }}</button>
                <a href="{{ url('/voters') }}" class="btn btn-outline-secondary" title="{{ t('common.reset') }}"><i class="bi bi-x-lg"></i></a></div>
        </div>
        @php($adv = collect(['name','relativeName','epicNo','mobile','gender','ageMin','ageMax','district','villageWard','houseNo','hasMobile','caste','politicalStatus','beneficiary','membershipNo','deleted'])->contains(fn($k) => $f->get($k) !== null))
        <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#adv">{{ t('list.advanced') }} <i class="bi bi-chevron-down"></i></a>
        <div id="adv" class="collapse {{ $adv ? 'show' : '' }}"><div class="row g-2 mt-1">
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.name') }}</label><input name="name" value="{{ $f->get('name') }}" class="form-control" dir="auto"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.relativeName') }}</label><input name="relativeName" value="{{ $f->get('relativeName') }}" class="form-control" dir="auto"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.epicNo') }}</label><input name="epicNo" value="{{ $f->get('epicNo') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.mobile') }}</label><input name="mobile" value="{{ $f->get('mobile') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.houseNo') }}</label><input name="houseNo" value="{{ $f->get('houseNo') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.gender') }}</label>
                <select name="gender" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach(['MALE','FEMALE','OTHER'] as $g)<option value="{{ $g }}" @selected($g === $f->get('gender'))>{{ t('gender.'.$g) }}</option>@endforeach</select></div>
            <div class="col-3 col-md-1"><label class="form-label">{{ t('list.ageFrom') }}</label><input name="ageMin" type="number" min="18" value="{{ $f->get('ageMin') }}" class="form-control"></div>
            <div class="col-3 col-md-1"><label class="form-label">{{ t('list.ageTo') }}</label><input name="ageMax" type="number" min="18" value="{{ $f->get('ageMax') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.district') }}</label>
                <select name="district" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($districts as $d)<option value="{{ $d }}" @selected($d === $f->get('district'))>{{ $d }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.villageWard') }}</label>
                <select name="villageWard" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($villages as $v)<option value="{{ $v }}" @selected($v === $f->get('villageWard'))>{{ $v }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('list.mobileAvailable') }}</label>
                <select name="hasMobile" class="form-select"><option value="">{{ t('common.any') }}</option>
                    <option value="true" @selected($f->get('hasMobile') === 'true')>{{ t('filter.withMobile') }}</option>
                    <option value="false" @selected($f->get('hasMobile') === 'false')>{{ t('filter.withoutMobile') }}</option></select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.caste') }}</label>
                <select name="caste" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($castes as $c)<option value="{{ $c }}" @selected($c === $f->get('caste'))>{{ $c }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.politicalStatus') }}</label>
                <select name="politicalStatus" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($politicalStatuses as $c)<option value="{{ $c }}" @selected($c === $f->get('politicalStatus'))>{{ $c }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('filter.beneficiary') }}</label><input name="beneficiary" value="{{ $f->get('beneficiary') }}" class="form-control" dir="auto"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.membershipNo') }}</label><input name="membershipNo" value="{{ $f->get('membershipNo') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.deleted') }}</label>
                <select name="deleted" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach(['exclude', 'only'] as $d)<option value="{{ $d }}" @selected($d === $f->get('deleted'))>{{ t('filter.deleted.'.$d) }}</option>@endforeach</select></div>
        </div></div>
        <input type="hidden" name="sort" value="{{ $f->sort }}"><input type="hidden" name="dir" value="{{ $f->dir }}"><input type="hidden" name="size" value="{{ $f->size }}">
    </div></form>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="me-auto text-muted small">{{ t('list.showing', $page->total() ? ($page->currentPage() - 1) * $page->perPage() + 1 : 0, ($page->currentPage() - 1) * $page->perPage() + $page->count(), $page->total()) }}
            @if($f->active())<span class="badge text-bg-warning ms-1">{{ t('list.filtered') }}</span>@endif</div>
        @if(can('MESSAGE_SEND'))<div class="btn-group">
            <a class="btn btn-sm btn-outline-primary" href="{{ url('/messages/compose') }}?{{ $f->queryWithSort() }}&channel=SMS"><i class="bi bi-chat-text"></i> SMS</a>
            <a class="btn btn-sm btn-outline-success" href="{{ url('/messages/compose') }}?{{ $f->queryWithSort() }}&channel=WHATSAPP"><i class="bi bi-whatsapp"></i> WhatsApp</a></div>@endif
        @if(can('VOTER_EXPORT'))
        <a class="btn btn-sm btn-success" href="{{ url('/voters/export/excel') }}?{{ $f->queryWithSort() }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a class="btn btn-sm btn-danger" href="{{ url('/voters/export/pdf') }}?{{ $f->queryWithSort() }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a class="btn btn-sm btn-secondary" target="_blank" href="{{ url('/voters/print') }}?{{ $f->queryWithSort() }}&autoprint=1"><i class="bi bi-printer"></i> {{ t('common.print') }}</a>
        @endif
        <form method="get" action="{{ url('/voters') }}" class="d-flex align-items-center gap-1">
            @foreach($f->hiddenParams() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="size" class="form-select form-select-sm" data-autosubmit>
                @foreach([10,25,50,100,250,500] as $s)<option value="{{ $s }}" @selected($s == $f->size)>{{ t('list.perPage', $s) }}</option>@endforeach</select>
        </form>
    </div>
    <div class="table-responsive bg-white border rounded"><table class="table table-hover table-striped mb-0">
        <thead class="table-light"><tr>
            @foreach(['assemblyNo' => 'list.col.ac', 'boothNo' => 'list.col.booth', 'serialNo' => 'list.col.sr', 'epicNo' => 'col.epicNo', 'name' => 'col.name',
                      'relativeName' => 'list.col.relative', 'gender' => 'col.gender', 'age' => 'col.age', 'villageWard' => 'col.villageWard', 'mobile' => 'col.mobile'] as $col => $label)
                <th><a href="{{ $f->sortLink($col) }}">{{ t($label) }} @if($f->sort === $col)<i class="bi {{ $f->dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down' }}"></i>@endif</a></th>
            @endforeach
            <th>{{ t('col.houseNo') }}</th><th>{{ t('col.address') }}</th><th class="text-end no-print">{{ t('common.actions') }}</th>
        </tr></thead>
        <tbody>
        @forelse($page as $v)
            <tr>
                <td>{{ $v->assembly_no }}</td><td>{{ $v->booth_no }}</td><td>{{ $v->serial_no }}</td><td><code>{{ $v->epic_no }}</code></td>
                <td><a href="{{ url('/voters/'.$v->id) }}">{{ $v->name }}</a>@if($v->name_local)<span class="local-text" dir="auto">{{ $v->name_local }}</span>@endif</td>
                <td>{{ $v->relationDisplay() }}@if($v->relative_name_local)<span class="local-text" dir="auto">{{ $v->relative_name_local }}</span>@endif</td>
                <td>{{ $v->genderLabel() }}</td><td>{{ $v->age }}</td><td>{{ $v->village_ward }}</td>
                <td>@if($v->mobile)<a href="tel:{{ $v->mobile }}">{{ $v->mobile }}</a>@endif</td>
                <td>@if($v->house_no)<a class="house-link" data-household target="_blank" href="{{ url('/voters/'.$v->id.'/household') }}" title="{{ t('household.tooltip') }}">{{ $v->house_no }}</a>@endif</td>
                <td class="small text-muted">{{ $v->fullAddress() }}@if($v->address_local)<span class="local-text" dir="auto">{{ $v->address_local }}</span>@endif</td>
                <td class="text-end text-nowrap no-print">
                    <a class="btn btn-sm btn-outline-primary" href="{{ url('/voters/'.$v->id) }}" title="{{ t('common.view') }}"><i class="bi bi-eye"></i></a>
                    @if(can('VOTER_EDIT'))<a class="btn btn-sm btn-outline-secondary" href="{{ url('/voters/'.$v->id.'/edit') }}" title="{{ t('common.edit') }}"><i class="bi bi-pencil"></i></a>@endif
                    @if(can('VOTER_DELETE'))<form action="{{ url('/voters/'.$v->id.'/delete') }}" method="post" class="d-inline" data-confirm="{{ t('common.confirmDelete') }}">@csrf
                        <button class="btn btn-sm btn-outline-danger" title="{{ t('common.delete') }}"><i class="bi bi-trash"></i></button></form>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="13" class="text-center text-muted py-4">{{ t('list.empty') }}</td></tr>
        @endforelse
        </tbody></table></div>
    @if($page->lastPage() > 1)
    @php($cur = $page->currentPage() - 1)
    <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center flex-wrap">
        <li class="page-item {{ $cur == 0 ? 'disabled' : '' }}"><a class="page-link" href="{{ $f->pageLink(0) }}">&laquo;</a></li>
        <li class="page-item {{ $cur == 0 ? 'disabled' : '' }}"><a class="page-link" href="{{ $f->pageLink(max(0, $cur - 1)) }}">&lsaquo;</a></li>
        @for($i = max(0, $cur - 3); $i <= min($page->lastPage() - 1, $cur + 3); $i++)
            <li class="page-item {{ $i == $cur ? 'active' : '' }}"><a class="page-link" href="{{ $f->pageLink($i) }}">{{ $i + 1 }}</a></li>
        @endfor
        <li class="page-item {{ $cur >= $page->lastPage() - 1 ? 'disabled' : '' }}"><a class="page-link" href="{{ $f->pageLink($cur + 1) }}">&rsaquo;</a></li>
        <li class="page-item {{ $cur >= $page->lastPage() - 1 ? 'disabled' : '' }}"><a class="page-link" href="{{ $f->pageLink($page->lastPage() - 1) }}">&raquo;</a></li>
    </ul></nav>
    @endif
</div>
@endsection
@push('modals') @include('partials.household-modal') @endpush
