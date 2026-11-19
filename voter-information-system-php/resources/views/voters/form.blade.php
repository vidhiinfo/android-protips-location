@extends('layouts.app')
@section('title', $voter->exists ? t('form.editTitle') : t('form.addTitle'))
@section('content')
@php($v = $voter)
<div class="container" style="max-width:1050px">
    <h4 class="mb-3">{{ $v->exists ? t('form.editTitle') . ': ' . $v->name : t('form.addTitle') }}</h4>
    <form method="post" action="{{ $v->exists ? url('/voters/'.$v->id) : url('/voters') }}" novalidate>@csrf
        @if(isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ t('form.fixErrors') }}</div>@endif
        <div class="card mb-3"><div class="card-header fw-semibold">{{ t('form.section.voter') }}</div><div class="card-body row g-3">
            @include('partials.input', ['name' => 'epic_no', 'label' => 'col.epicNo', 'cls' => 'col-md-4', 'value' => $v->epic_no])
            @include('partials.input', ['name' => 'name', 'label' => 'form.nameEnglish', 'cls' => 'col-md-4', 'value' => $v->name])
            @include('partials.input', ['name' => 'name_local', 'label' => 'col.nameLocal', 'cls' => 'col-md-4', 'value' => $v->name_local, 'local' => true])
            <div class="col-md-2"><label class="form-label">{{ t('col.relation') }}</label>
                <select name="relation_type" class="form-select">@foreach(\App\Models\Voter::RELATIONS as $r)<option value="{{ $r }}" @selected($r === ($v->relation_type ?: 'FATHER'))>{{ t('relation.'.$r) }}</option>@endforeach</select></div>
            @include('partials.input', ['name' => 'relative_name', 'label' => 'col.relativeName', 'cls' => 'col-md-4', 'value' => $v->relative_name])
            @include('partials.input', ['name' => 'relative_name_local', 'label' => 'col.relativeNameLocal', 'cls' => 'col-md-6', 'value' => $v->relative_name_local, 'local' => true])
            @php($ge = isset($errors) ? $errors->first('gender') : null)
            <div class="col-md-2"><label class="form-label">{{ t('col.gender') }}</label>
                <select name="gender" class="form-select {{ $ge ? 'is-invalid' : '' }}"><option value="">--</option>
                    @foreach(\App\Models\Voter::GENDERS as $g)<option value="{{ $g }}" @selected($g === $v->gender)>{{ t('gender.'.$g) }}</option>@endforeach</select>
                @if($ge)<div class="invalid-feedback">{{ $ge }}</div>@endif</div>
            @include('partials.input', ['name' => 'age', 'label' => 'col.age', 'cls' => 'col-md-2', 'type' => 'number', 'value' => $v->age])
            @include('partials.input', ['name' => 'category', 'label' => 'col.category', 'cls' => 'col-md-3', 'value' => $v->category])
        </div></div>
        <div class="card mb-3"><div class="card-header fw-semibold">{{ t('form.section.booth') }}</div><div class="card-body row g-3">
            @include('partials.input', ['name' => 'assembly_no', 'label' => 'col.assemblyNo', 'cls' => 'col-md-3', 'type' => 'number', 'value' => $v->assembly_no, 'readonly' => $me->scoped(), 'list' => 'assemblyList'])
            <datalist id="assemblyList">@foreach($masterAssemblies as $a)<option value="{{ $a->assembly_no }}">{{ $a->name }}</option>@endforeach</datalist>
            @include('partials.input', ['name' => 'assembly_name', 'label' => 'col.assemblyName', 'cls' => 'col-md-3', 'value' => $v->assembly_name, 'local' => true])
            @include('partials.input', ['name' => 'booth_no', 'label' => 'col.boothNo', 'cls' => 'col-md-3', 'type' => 'number', 'value' => $v->booth_no, 'list' => 'boothList'])
            <datalist id="boothList">@foreach($masterBooths as $b)<option value="{{ $b->booth_no }}">{{ $b->assembly_no }}/{{ $b->booth_no }} {{ $b->name }}</option>@endforeach</datalist>
            @include('partials.input', ['name' => 'serial_no', 'label' => 'col.serialNo', 'cls' => 'col-md-3', 'type' => 'number', 'value' => $v->serial_no])
            @include('partials.input', ['name' => 'booth_name', 'label' => 'col.boothName', 'cls' => 'col-12', 'value' => $v->booth_name, 'local' => true])
            <div class="col-12 form-text mt-0">{{ t('form.mastersHint') }}</div>
        </div></div>
        <div class="card mb-3"><div class="card-header fw-semibold">{{ t('form.section.address') }}</div><div class="card-body row g-3">
            @include('partials.input', ['name' => 'house_no', 'label' => 'col.houseNo', 'cls' => 'col-md-2', 'value' => $v->house_no])
            @include('partials.input', ['name' => 'address', 'label' => 'col.address', 'cls' => 'col-md-5', 'value' => $v->address])
            @include('partials.input', ['name' => 'address_local', 'label' => 'col.addressLocal', 'cls' => 'col-md-5', 'value' => $v->address_local, 'local' => true])
            @include('partials.input', ['name' => 'village_ward', 'label' => 'col.villageWard', 'cls' => 'col-md-3', 'value' => $v->village_ward, 'local' => true])
            @if(\App\Models\Voter::showWard())@include('partials.input', ['name' => 'ward_no', 'label' => 'col.wardNo', 'cls' => 'col-md-2', 'type' => 'number', 'value' => $v->ward_no])@endif
            @include('partials.input', ['name' => 'district', 'label' => 'col.district', 'cls' => 'col-md-4', 'value' => $v->district, 'local' => true])
            @include('partials.input', ['name' => 'pincode', 'label' => 'col.pincode', 'cls' => 'col-md-3', 'value' => $v->pincode])
            @include('partials.input', ['name' => 'mobile', 'label' => 'col.mobile', 'cls' => 'col-md-4', 'type' => 'tel', 'value' => $v->mobile])
            @include('partials.input', ['name' => 'alt_mobile', 'label' => 'col.altMobile', 'cls' => 'col-md-4', 'type' => 'tel', 'value' => $v->alt_mobile])
            @include('partials.input', ['name' => 'email', 'label' => 'col.email', 'cls' => 'col-md-4', 'type' => 'email', 'value' => $v->email])
            <div class="col-12"><label class="form-label">{{ t('col.remarks') }}</label><textarea name="remarks" class="form-control" rows="2" maxlength="500" dir="auto">{{ $v->remarks }}</textarea></div>
        </div></div>
        <div class="card mb-3"><div class="card-header fw-semibold">{{ t('form.section.profile') }}</div><div class="card-body row g-3">
            @foreach(\App\Models\Voter::PROFILE as $field => [$key, $type])
                @if($type === 'checkbox')
                <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2">
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input class="form-check-input" type="checkbox" name="{{ $field }}" id="{{ $field }}" value="1" @checked($v->$field)>
                    <label class="form-check-label" for="{{ $field }}">{{ t('col.'.$key) }}</label></div></div>
                @elseif($type === 'date')
                @include('partials.input', ['name' => $field, 'label' => 'col.'.$key, 'cls' => 'col-md-3', 'type' => 'date',
                    'value' => $v->$field instanceof \DateTimeInterface ? $v->$field->format('Y-m-d') : ($rawDates[$field] ?? $v->getRawOriginal($field))])
                @elseif($field === 'caste' && $casteTree)
                {{-- Caste from the Caste master; Sub Caste lists only the sub castes of the chosen caste (public/js/app.js) --}}
                @php($castes = array_keys($casteTree))
                @if($v->caste && !in_array($v->caste, $castes))@php($castes[] = $v->caste)@endif
                <div class="col-md-3"><label class="form-label" for="caste">{{ t('col.caste') }}</label>
                    <select name="caste" id="caste" class="form-select {{ isset($errors) && $errors->first('caste') ? 'is-invalid' : '' }}" data-caste-select data-sub-select="sub_caste"
                            data-map='@json((object) $casteTree)' data-labels='@json((object) \App\Models\Caste::labels())'>
                        <option value="">--</option>
                        @foreach($castes as $c)<option value="{{ $c }}" @selected($c === $v->caste)>{{ \App\Models\Caste::labels()[$c] ?? $c }}</option>@endforeach
                    </select></div>
                @elseif($field === 'sub_caste' && $casteTree)
                @php($subs = $casteTree[$v->caste] ?? [])
                @if($v->sub_caste && !in_array($v->sub_caste, $subs))@php($subs[] = $v->sub_caste)@endif
                <div class="col-md-3"><label class="form-label" for="sub_caste">{{ t('col.subCaste') }}</label>
                    <select name="sub_caste" id="sub_caste" class="form-select">
                        <option value="">--</option>
                        @foreach($subs as $sc)<option value="{{ $sc }}" @selected($sc === $v->sub_caste)>{{ \App\Models\Caste::labels()[$sc] ?? $sc }}</option>@endforeach
                    </select></div>
                @elseif(\App\Support\StatusMasters::has($field))
                {{-- Social / Political / Organisational Status from their masters (Masters menu) --}}
                @php($cur = old($field, $v->$field))
                <div class="col-md-3"><label class="form-label" for="{{ $field }}">{{ t('col.'.$key) }}</label>
                    <select name="{{ $field }}" id="{{ $field }}" class="form-select {{ isset($errors) && $errors->first($field) ? 'is-invalid' : '' }}">
                        <option value="">--</option>
                        @foreach(\App\Support\StatusMasters::options($field, [$cur]) as $val => $label)<option value="{{ $val }}" @selected((string) $val === (string) $cur)>{{ $label }}</option>@endforeach
                    </select></div>
                @else
                @include('partials.input', ['name' => $field, 'label' => 'col.'.$key, 'cls' => 'col-md-3', 'value' => $v->$field, 'local' => true])
                @endif
            @endforeach
        </div></div>
        <div class="d-flex gap-2 mb-4">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ t('common.save') }}</button>
            <a href="{{ $v->exists ? url('/voters/'.$v->id) : url('/voters') }}" class="btn btn-outline-secondary">{{ t('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
