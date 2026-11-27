@extends('layouts.app')
@section('title', t('notif.send'))
@section('content')
<div class="container" style="max-width:860px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-megaphone"></i> {{ t('notif.send') }}</h4>
    <p class="small text-muted">{{ t('notif.sendIntro') }}</p>
    <form method="post" action="{{ url('/notifications/send') }}" class="card card-body">@csrf
        <div class="row g-3">
            <div class="col-12"><label class="form-label">{{ t('notif.titleField') }} *</label><input name="title" class="form-control" maxlength="200" required value="{{ old('title') }}" placeholder="{{ t('notif.titlePh') }}"></div>
            <div class="col-12"><label class="form-label">{{ t('notif.body') }}</label><textarea name="body" class="form-control" rows="3" maxlength="1000" data-voice>{{ old('body') }}</textarea></div>
            <div class="col-md-4"><label class="form-label">{{ t('notif.to') }}</label><select name="target" class="form-select">
                @foreach(['all', 'workerrole', 'loginrole'] as $tg)<option value="{{ $tg }}" @selected(old('target', 'all') === $tg)>{{ t('notif.target.' . $tg) }}</option>@endforeach</select></div>
            <div class="col-md-8"><label class="form-label">{{ t('notif.role') }}</label><select name="role" class="form-select"><option value="">-</option>
                <optgroup label="{{ t('notif.target.workerrole') }}">@foreach($workerRoles as $c)<option value="{{ $c }}" @selected(old('role') === $c)>{{ role_label($c) }}</option>@endforeach</optgroup>
                <optgroup label="{{ t('notif.target.loginrole') }}">@foreach($loginRoles as $c => $n)<option value="{{ $c }}" @selected(old('role') === $c)>{{ $n }}</option>@endforeach</optgroup></select>
                <div class="form-text">{{ t('notif.roleHint') }}</div></div>
            <div class="col-md-4"><label class="form-label">{{ t('col.assemblyNo') }}</label><select name="assembly" class="form-select" @disabled($limitAssembly)><option value="">{{ t('common.all') }}</option>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == old('assembly', $limitAssembly))>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ t('org.mandal') }}</label><select name="mandal" class="form-select"><option value="">{{ t('common.all') }}</option>
                @foreach($mandals as $m)<option value="{{ $m->id }}" @selected($m->id == old('mandal'))>{{ $m->assembly_no }} - {{ $m->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ t('col.boothNo') }}</label><input type="number" name="booth" class="form-control" min="1" value="{{ old('booth') }}"></div>
            <div class="col-12"><label class="form-label">{{ t('notif.link') }}</label><input name="url" class="form-control" maxlength="300" value="{{ old('url') }}" placeholder="{{ url('/my/tasks') }}"><div class="form-text">{{ t('notif.linkHint') }}</div></div>
        </div>
        <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-send"></i> {{ t('notif.sendBtn') }}</button>
            <button class="btn btn-outline-secondary" name="preview" value="1">{{ t('notif.preview') }}</button></div>
        <div class="form-text">{{ t('notif.loginOnly') }}</div>
    </form>
    @if($sent->isNotEmpty())
    <h6 class="mt-4">{{ t('notif.sentList') }}</h6>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>{{ t('notif.titleField') }}</th><th>{{ t('notif.when') }}</th><th class="text-end">{{ t('notif.reached') }}</th><th class="text-end">{{ t('notif.seen') }}</th></tr></thead>
        <tbody>@foreach($sent as $s)<tr><td>{{ $s->title }}@if($s->body)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($s->body, 90) }}</div>@endif</td>
            <td class="small text-nowrap">{{ \Carbon\Carbon::parse($s->created_at)->format('d-m-Y H:i') }}</td><td class="text-end">{{ $s->n }}</td><td class="text-end">{{ $s->seen }}</td></tr>@endforeach</tbody>
    </table></div>
    @endif
</div>
@endsection
