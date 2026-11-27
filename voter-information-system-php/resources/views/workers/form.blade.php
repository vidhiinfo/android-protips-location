@extends('layouts.app')
@section('title', t('worker.title'))
@section('content')
<div class="container" style="max-width:760px">
    @include('partials.flash')
    <h4 class="mb-3"><i class="bi bi-person-badge"></i> {{ $w->exists ? $w->name : t('worker.add') }}</h4>
    <form method="post" action="{{ url($w->exists ? '/workers/'.$w->id : '/workers') }}" class="card card-body">@csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ t('worker.name') }} *</label><input name="name" value="{{ old('name', $w->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="120">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">{{ t('col.mobile') }}</label><input name="mobile" value="{{ old('mobile', $w->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" inputmode="tel" maxlength="10">@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">{{ t('col.assemblyNo') }} *</label><select name="assembly_no" class="form-select" @disabled($me->scoped())>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == old('assembly_no', $w->assembly_no))>{{ $no }} - {{ $name }}</option>@endforeach</select>
                @if($me->scoped())<input type="hidden" name="assembly_no" value="{{ $me->workAssembly() }}">@endif</div>
            <div class="col-md-4"><label class="form-label">{{ t('col.boothNo') }}</label><select name="booth_no" class="form-select"><option value="">-</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == old('booth_no', $w->booth_no))>{{ $b }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ t('worker.role') }} *</label><select name="role" class="form-select" data-role-select>
                @foreach(\App\Models\Worker::ROLES as $ro)@continue($ro === 'LS_INCHARGE' && $me->scoped())<option value="{{ $ro }}" @selected($ro === old('role', $w->role))>{{ t('worker.role.'.$ro) }}</option>@endforeach</select>
                <div class="form-text">{{ t('org.roleHint') }}</div></div>
            @if(count($mandals))
            <div class="col-md-6" data-mandal-field><label class="form-label">{{ t('org.mandal') }}</label><select name="mandal_id" class="form-select"><option value="">-</option>
                @foreach($mandals as $m)<option value="{{ $m->id }}" @selected($m->id == old('mandal_id', $w->mandal_id))>{{ $m->assembly_no }} - {{ $m->name }}</option>@endforeach</select>
                <div class="form-text">{{ t('org.mandalHint') }}</div></div>
            @endif
            <div class="col-md-6"><label class="form-label">{{ t('worker.login') }}</label><select name="username" class="form-select"><option value="">{{ t('worker.noLogin') }}</option>
                @foreach($usernames as $u)<option value="{{ $u }}" @selected($u === old('username', $w->username))>{{ $u }}</option>@endforeach</select>
                <div class="form-text">{{ t('worker.loginHint') }}</div></div>
            <div class="col-md-6 d-flex align-items-center"><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $w->active))><label class="form-check-label" for="active">{{ t('master.active') }}</label></div></div>
            <div class="col-12"><label class="form-label">{{ t('col.remarks') }}</label><input name="notes" value="{{ old('notes', $w->notes) }}" class="form-control" maxlength="300"></div>
        </div>
        <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ t('common.save') }}</button>
            <a href="{{ url($w->exists ? '/workers/'.$w->id : '/workers') }}" class="btn btn-outline-secondary">{{ t('common.cancel') }}</a></div>
    </form>
</div>
@endsection
