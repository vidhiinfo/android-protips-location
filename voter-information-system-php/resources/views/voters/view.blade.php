@extends('layouts.app')
@section('title', $voter->name)
@section('content')
@php($v = $voter)
<div class="container" style="max-width:1000px">
    @include('partials.flash')
    <div class="d-flex align-items-center gap-2 mb-3 no-print">
        <a href="{{ url('/voters') }}" class="btn btn-sm btn-outline-secondary me-auto"><i class="bi bi-arrow-left"></i> {{ t('common.back') }}</a>
        @if(can('MESSAGE_SEND') && $v->mobile)
            <a class="btn btn-sm btn-outline-primary" href="{{ url('/messages/compose') }}?epicNo={{ urlencode($v->epic_no) }}&channel=SMS"><i class="bi bi-chat-text"></i> SMS</a>
            <a class="btn btn-sm btn-outline-success" href="{{ url('/messages/compose') }}?epicNo={{ urlencode($v->epic_no) }}&channel=WHATSAPP"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            @unless($optedOut)
            <form action="{{ url('/messages/optouts') }}" method="post" data-confirm="{{ t('msg.optOutConfirm') }}">@csrf
                <input type="hidden" name="mobile" value="{{ $v->mobile }}"><input type="hidden" name="reason" value="{{ t('msg.optOutReasonVoter') }}">
                <input type="hidden" name="back" value="/voters/{{ $v->id }}">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-bell-slash"></i> {{ t('msg.optOut') }}</button></form>
            @endunless
        @endif
        <button class="btn btn-sm btn-secondary" data-print><i class="bi bi-printer"></i> {{ t('view.printSlip') }}</button>
        @if(can('VOTER_EDIT'))<a href="{{ url('/voters/'.$v->id.'/edit') }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> {{ t('common.edit') }}</a>@endif
        @if(can('VOTER_DELETE'))<form action="{{ url('/voters/'.$v->id.'/delete') }}" method="post" data-confirm="{{ t('common.confirmDelete') }}">@csrf
            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> {{ t('common.delete') }}</button></form>@endif
    </div>
    <div class="card mb-3"><div class="card-body">
        <h4 class="mb-0">{{ $v->name }} @if($v->deleted)<span class="badge text-bg-danger fs-6 align-middle">{{ t('col.deleted') }}</span>@endif</h4>
        @if($v->name_local)<div class="fs-5" dir="auto">{{ $v->name_local }}</div>@endif
        @if($v->relative_name)<div class="text-muted mb-3">{{ $v->relationDisplay() }}@if($v->relative_name_local)<span dir="auto"> / {{ $v->relative_name_local }}</span>@endif</div>@endif
        <dl class="row detail mb-0">
            <dt class="col-sm-3">{{ t('col.epicNo') }}</dt><dd class="col-sm-9"><code>{{ $v->epic_no }}</code></dd>
            <dt class="col-sm-3">{{ t('col.gender') }} / {{ t('col.age') }}</dt><dd class="col-sm-9">{{ $v->genderLabel() }}{{ $v->age ? ' / '.$v->age : '' }}</dd>
            <dt class="col-sm-3">{{ t('col.assemblyNo') }}</dt><dd class="col-sm-9">{{ $v->assembly_no }}{{ $v->assembly_name ? ' - '.$v->assembly_name : '' }}</dd>
            @if($v->ward_no)<dt class="col-sm-3">{{ t('col.wardNo') }}</dt><dd class="col-sm-9">{{ $v->ward_no }}</dd>@endif
            <dt class="col-sm-3">{{ t('col.boothNo') }}</dt><dd class="col-sm-9">{{ $v->booth_no }}{{ $v->booth_name ? ' - '.$v->booth_name : '' }}</dd>
            <dt class="col-sm-3">{{ t('col.serialNo') }}</dt><dd class="col-sm-9">{{ $v->serial_no ?? '-' }}</dd>
            <dt class="col-sm-3">{{ t('col.houseNo') }}</dt><dd class="col-sm-9">@if($v->house_no)<a class="house-link" data-household href="{{ url('/voters/'.$v->id.'/household') }}">{{ $v->house_no }}</a>@else - @endif</dd>
            <dt class="col-sm-3">{{ t('col.address') }}</dt><dd class="col-sm-9">{{ $v->fullAddress() ?: '-' }}@if($v->address_local)<span class="local-text" dir="auto">{{ $v->address_local }}</span>@endif</dd>
            <dt class="col-sm-3">{{ t('col.mobile') }}</dt><dd class="col-sm-9">{{ $v->mobile ?: '-' }} @if($optedOut)<span class="badge text-bg-secondary ms-1"><i class="bi bi-bell-slash"></i> {{ t('msg.optedOut') }}</span>@endif</dd>
            <dt class="col-sm-3">{{ t('col.altMobile') }}</dt><dd class="col-sm-9">{{ $v->alt_mobile ?: '-' }}</dd>
            <dt class="col-sm-3">{{ t('col.email') }}</dt><dd class="col-sm-9">{{ $v->email ?: '-' }}</dd>
            <dt class="col-sm-3">{{ t('col.category') }}</dt><dd class="col-sm-9">{{ $v->category ?: '-' }}</dd>
            <dt class="col-sm-3">{{ t('col.remarks') }}</dt><dd class="col-sm-9" dir="auto">{{ $v->remarks ?: '-' }}</dd>
            <dt class="col-sm-3 no-print">{{ t('view.updated') }}</dt><dd class="col-sm-9 no-print">{{ $v->updated_at?->format('d-m-Y H:i') }}</dd>
        </dl>
    </div></div>
    @php($profile = array_filter(array_map(fn ($f) => $v->profileValue($f), array_combine(array_keys(\App\Models\Voter::PROFILE), array_keys(\App\Models\Voter::PROFILE))), fn ($x) => $x !== null))
    @unset($profile['deleted'])
    @if($profile)
    <div class="card mb-3 no-print"><div class="card-header fw-semibold"><i class="bi bi-person-vcard"></i> {{ t('form.section.profile') }}</div><div class="card-body">
        <dl class="row detail mb-0">
            @foreach($profile as $field => $val)
            <dt class="col-sm-3">{{ t('col.'.\App\Models\Voter::PROFILE[$field][0]) }}</dt><dd class="col-sm-9" dir="auto">{{ $val }}</dd>
            @endforeach
        </dl>
    </div></div>
    @endif
    @if(count($household) > 1)
    <div class="card mb-4 no-print"><div class="card-header fw-semibold"><i class="bi bi-house"></i> {{ t('household.title') }} <span class="badge text-bg-primary">{{ count($household) }}</span></div>
        <ul class="list-group list-group-flush">@foreach($household as $h) @if($h->id !== $v->id)
            <li class="list-group-item d-flex justify-content-between"><span><a href="{{ url('/voters/'.$h->id) }}">{{ $h->name }}</a>
                @if($h->name_local)<span class="text-muted" dir="auto"> / {{ $h->name_local }}</span>@endif</span>
                <span class="text-muted small">{{ $h->genderLabel() }}, {{ $h->age }}</span></li>
        @endif @endforeach</ul></div>
    @endif
</div>
@endsection
@push('modals') @include('partials.household-modal') @endpush
