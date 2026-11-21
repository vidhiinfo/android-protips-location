@extends('layouts.app')
@section('title', t('nav.voterList'))
@section('content')
<div class="container-fluid px-3">
    @include('partials.flash')
    @if(!empty($searchSlow))<div class="alert alert-warning py-2"><i class="bi bi-hourglass-split"></i> {{ t('list.searchSlow') }}</div>@endif
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('list.totalVoters') }}</div><div class="num">{{ $total }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('gender.MALE') }}</div><div class="num">{{ $genderCounts['MALE'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('gender.FEMALE') }}</div><div class="num">{{ $genderCounts['FEMALE'] }}</div></div></div>
        <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ t('list.matching') }}</div><div class="num text-primary">{{ $page->total() }}{{ !empty($page->capped) ? '+' : '' }}</div></div></div>
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
            @include('partials.ward-booth', ['wards' => $wards, 'wardNo' => $f->get('wardNo'), 'wardMap' => $wardMap, 'booths' => $booths, 'boothNo' => $f->get('boothNo'), 'cls' => 'col-6 col-md-1'])
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> {{ t('common.search') }}</button>
                <a href="{{ url('/voters') }}" class="btn btn-outline-secondary" title="{{ t('common.reset') }}"><i class="bi bi-x-lg"></i></a></div>
        </div>
        @php($adv = collect(['name','relativeName','epicNo','mobile','gender','ageMin','ageMax','district','villageWard','houseNo','hasMobile','caste','subCaste','politicalStatus','socialStatus','orgStatus','sentiment','beneficiary','membershipNo','deleted','workerId','voted','consent'])->contains(fn($k) => $f->get($k) !== null))
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
                <select name="caste" class="form-select" data-caste-select data-sub-select="f-subCaste" data-map='@json((object) $casteTreeAll)' data-all='@json($subCastes)' data-labels='@json((object) \App\Models\Caste::labels())'>
                    <option value="">{{ t('common.all') }}</option>
                    @foreach($castes as $c)<option value="{{ $c }}" @selected($c === $f->get('caste'))>{{ \App\Models\Caste::labels()[$c] ?? $c }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.subCaste') }}</label>
                <select name="subCaste" id="f-subCaste" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($f->get('caste') ? ($casteTreeAll[$f->get('caste')] ?? []) : $subCastes as $c)<option value="{{ $c }}" @selected($c === $f->get('subCaste'))>{{ \App\Models\Caste::labels()[$c] ?? $c }}</option>@endforeach</select></div>
            @foreach(['politicalStatus' => 'col.politicalStatus', 'socialStatus' => 'col.socialStatus', 'orgStatus' => 'col.organisationalStatus'] as $sk => $sl)
            <div class="col-6 col-md-2"><label class="form-label">{{ t($sl) }}</label>
                <select name="{{ $sk }}" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($statusFilter[$sk] as $o => $lbl)<option value="{{ $o }}" @selected((string) $o === (string) $f->get($sk))>{{ $lbl }}</option>@endforeach</select></div>
            @endforeach
            <div class="col-6 col-md-2"><label class="form-label">{{ t('filter.leaning') }}</label>
                <select name="sentiment" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach(['S' => 'an.support', 'N' => 'an.neutral', 'O' => 'an.opposition', 'X' => 'an.other', 'U' => 'an.unknown'] as $o => $lbl)<option value="{{ $o }}" @selected($o === $f->get('sentiment'))>{{ t($lbl) }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('filter.beneficiary') }}</label>
                <select name="beneficiary" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach($statusFilter['beneficiary'] as $o => $lbl)<option value="{{ $o }}" @selected((string) $o === (string) $f->get('beneficiary'))>{{ $lbl }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.membershipNo') }}</label><input name="membershipNo" value="{{ $f->get('membershipNo') }}" class="form-control"></div>
            @if($workers->count() && !$me->workerId())
            <div class="col-6 col-md-2"><label class="form-label">{{ t('worker.worker') }}</label>
                <select name="workerId" class="form-select"><option value="">{{ t('common.all') }}</option><option value="0" @selected($f->get('workerId') === 0)>{{ t('worker.notAssigned') }}</option>
                    @foreach($workers as $wk)<option value="{{ $wk->id }}" @selected($wk->id === $f->get('workerId'))>{{ $wk->label() }}</option>@endforeach</select></div>
            @endif
            <div class="col-6 col-md-2"><label class="form-label">{{ t('poll.votedFilter') }}</label>
                <select name="voted" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach(['yes', 'no'] as $vt)<option value="{{ $vt }}" @selected($vt === $f->get('voted'))>{{ t('poll.voted.'.$vt) }}</option>@endforeach</select></div>
            @if(\App\Services\Privacy\PrivacyService::enabled())<div class="col-6 col-md-2"><label class="form-label">{{ t('privacy.consentLabel') }}</label>
                <select name="consent" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach(['Y', 'N', 'U'] as $c)<option value="{{ $c }}" @selected($c === $f->get('consent'))>{{ t('privacy.consent.' . $c) }}</option>@endforeach</select></div>@endif
            <div class="col-6 col-md-2"><label class="form-label">{{ t('col.deleted') }}</label>
                <select name="deleted" class="form-select"><option value="">{{ t('common.all') }}</option>
                    @foreach(['exclude', 'only'] as $d)<option value="{{ $d }}" @selected($d === $f->get('deleted'))>{{ t('filter.deleted.'.$d) }}</option>@endforeach</select></div>
        </div></div>
        <input type="hidden" name="sort" value="{{ $f->sort }}"><input type="hidden" name="dir" value="{{ $f->dir }}"><input type="hidden" name="size" value="{{ $f->size }}">
    </div></form>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <div class="me-auto text-muted small">{{ t('list.showing', $page->total() ? ($page->currentPage() - 1) * $page->perPage() + 1 : 0, ($page->currentPage() - 1) * $page->perPage() + $page->count(), $page->total() . (!empty($page->capped) ? '+' : '')) }}
            @if($f->active())<span class="badge text-bg-warning ms-1">{{ t('list.filtered') }}</span>@endif</div>
        @if(can('MESSAGE_SEND'))<div class="btn-group">
            <a class="btn btn-sm btn-outline-primary" href="{{ url('/messages/compose') }}?{{ $f->queryWithSort() }}&channel=SMS"><i class="bi bi-chat-text"></i> SMS</a>
            <a class="btn btn-sm btn-outline-success" href="{{ url('/messages/compose') }}?{{ $f->queryWithSort() }}&channel=WHATSAPP"><i class="bi bi-whatsapp"></i> WhatsApp</a></div>@endif
        @if(can('VOTER_EXPORT'))
        <a class="btn btn-sm btn-success" href="{{ url('/voters/export/excel') }}?{{ $f->queryWithSort() }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a class="btn btn-sm btn-outline-success" href="{{ url('/voters/export/survey') }}?{{ $f->queryWithSort() }}" title="{{ t('survey.hint') }}"><i class="bi bi-clipboard-check"></i> {{ t('survey.sheet') }}</a>
        <a class="btn btn-sm btn-danger" href="{{ url('/voters/export/pdf') }}?{{ $f->queryWithSort() }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a class="btn btn-sm btn-outline-danger" href="{{ url('/voters/export/slips') }}?{{ $f->queryWithSort() }}" title="{{ t('slip.hint') }}"><i class="bi bi-ticket-perforated"></i> {{ t('slip.title') }}</a>
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
            @if(can('VOTER_EDIT'))<th class="no-print"><input type="checkbox" class="form-check-input" data-check-all title="{{ t('bulk.selectPage') }}"></th>@endif
            @foreach(array_filter(['assemblyNo' => 'list.col.ac', 'wardNo' => \App\Models\Voter::showWard() ? 'col.wardNo' : null, 'boothNo' => 'list.col.booth', 'serialNo' => 'list.col.sr', 'epicNo' => 'col.epicNo', 'name' => 'col.name',
                      'relativeName' => 'list.col.relative', 'gender' => 'col.gender', 'age' => 'col.age', 'villageWard' => 'col.villageWard', 'mobile' => 'col.mobile']) as $col => $label)
                <th><a href="{{ $f->sortLink($col) }}">{{ t($label) }} @if($f->sort === $col)<i class="bi {{ $f->dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down' }}"></i>@endif</a></th>
            @endforeach
            <th>{{ t('col.houseNo') }}</th><th>{{ t('col.address') }}</th><th class="text-end no-print">{{ t('common.actions') }}</th>
        </tr></thead>
        <tbody>
        @forelse($page as $v)
            <tr>
                @if(can('VOTER_EDIT'))<td class="no-print"><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $v->id }}" form="bulkForm" data-check></td>@endif
                <td>{{ $v->assembly_no }}</td>@if(\App\Models\Voter::showWard())<td>{{ $v->ward_no }}</td>@endif<td>{{ $v->booth_no }}</td><td>{{ $v->serial_no }}</td><td><code>{{ $v->epic_no }}</code></td>
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
            <tr><td colspan="14" class="text-center text-muted py-4">{{ t('list.empty') }}</td></tr>
        @endforelse
        </tbody></table></div>
    @if(can('VOTER_EDIT'))
    <form method="post" action="{{ url('/voters/bulk') }}" id="bulkForm" class="bulk-bar card card-body py-2 mt-2 shadow-sm d-none no-print" data-bulk-bar>@csrf
        <input type="hidden" name="back" value="?{{ $f->queryWithSort() }}&page={{ $page->currentPage() - 1 }}">
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div class="fw-semibold me-2"><i class="bi bi-check2-square"></i> <span data-bulk-count>0</span> {{ t('bulk.selected') }}</div>
            @php($tree = \App\Models\Caste::tree())
            <div><label class="form-label small mb-0">{{ t('col.caste') }}</label>
                <select name="caste" class="form-select form-select-sm" data-caste-select data-sub-select="bulk-sub" data-map='@json((object) $tree)' data-labels='@json((object) \App\Models\Caste::labels())'>
                    <option value="">{{ t('bulk.keep') }}</option>@foreach(array_keys($tree) as $c)<option value="{{ $c }}">{{ \App\Models\Caste::labels()[$c] ?? $c }}</option>@endforeach<option value="-">{{ t('bulk.clear') }}</option></select></div>
            <div><label class="form-label small mb-0">{{ t('col.subCaste') }}</label>
                <select name="sub_caste" id="bulk-sub" class="form-select form-select-sm"><option value="">{{ t('bulk.keep') }}</option></select></div>
            @foreach(['political_status' => 'col.politicalStatus', 'social_status' => 'col.socialStatus', 'organisational_status' => 'col.organisationalStatus'] as $sf => $sl)
            <div><label class="form-label small mb-0">{{ t($sl) }}</label>
                @if(\App\Support\StatusMasters::rows($sf))
                <select name="{{ $sf }}" class="form-select form-select-sm"><option value="">{{ t('bulk.keep') }}</option>
                    @foreach(\App\Support\StatusMasters::options($sf) as $o => $lbl)<option value="{{ $o }}">{{ $lbl }}</option>@endforeach<option value="-">{{ t('bulk.clear') }}</option></select>
                @else<input name="{{ $sf }}" class="form-control form-control-sm" placeholder="{{ t('bulk.keep') }}" maxlength="100">@endif</div>
            @endforeach
            <div><label class="form-label small mb-0">{{ t('col.placeOfOrigin') }}</label><input name="place_of_origin" class="form-control form-control-sm" placeholder="{{ t('bulk.keep') }}" maxlength="100"></div>
            @if(can('WORKER_MANAGE') && $workers->count())
            <div><label class="form-label small mb-0">{{ t('worker.worker') }}</label>
                <select name="worker_id" class="form-select form-select-sm"><option value="">{{ t('bulk.keep') }}</option>
                    @foreach($workers as $wk)<option value="{{ $wk->id }}">{{ $wk->label() }}</option>@endforeach<option value="-">{{ t('bulk.clear') }}</option></select></div>
            @endif
            <button class="btn btn-sm btn-primary" data-confirm-bulk="{{ t('bulk.confirm') }}"><i class="bi bi-check2-all"></i> {{ t('bulk.apply') }}</button>
        </div>
        <div class="small text-muted mt-1">{{ t('bulk.hint') }}</div>
    </form>
    @endif
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
