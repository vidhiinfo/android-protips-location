@extends('layouts.app')
@section('title', t('settings.title'))
@section('content')
@php($icons = ['offline' => 'cloud-slash', 'login' => 'shield-lock', 'display' => 'display', 'smart' => 'magic', 'planner' => 'bullseye', 'messaging' => 'chat-dots'])
<div class="container px-3" style="max-width:900px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-toggles"></i> {{ t('settings.title') }}</h4>
    <p class="small text-muted">{{ t('settings.introAll') }}</p>
    <form method="post" action="{{ url('/admin/settings') }}" enctype="multipart/form-data">@csrf
    @foreach($groups as $g)
    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-{{ $icons[$g] }}"></i> {{ t('settings.group.' . $g) }}</div>
        <div class="card-body">
        @foreach($items[$g] as $name => $it)
            <div class="row g-2 align-items-start mb-3 pb-2 border-bottom">
                <div class="col-md-6">
                    <label class="fw-semibold small d-block" for="s-{{ $name }}">{{ t('settings.s.' . $name) }}</label>
                    <div class="small text-muted">{{ t('settings.h.' . $name) }}</div>
                </div>
                <div class="col-md-6">
                    @if($it['type'] === 'bool')
                        <input type="hidden" name="s[{{ $name }}]" value="0">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="s-{{ $name }}" name="s[{{ $name }}]" value="1" @checked($it['value'])>
                            <label class="form-check-label small" for="s-{{ $name }}">{{ $it['value'] ? t('settings.on') : t('settings.off') }}</label></div>
                    @elseif($it['type'] === 'int')
                        <div class="input-group input-group-sm" style="max-width:14rem"><input type="number" class="form-control" id="s-{{ $name }}" name="s[{{ $name }}]" value="{{ $it['value'] }}" min="{{ $it['rule'][0] }}" max="{{ $it['rule'][1] }}">
                            <span class="input-group-text">{{ t('settings.u.' . $name) }}</span></div>
                    @elseif($it['type'] === 'image')
                        <div class="d-flex align-items-center gap-3">
                            <span class="settings-photo">@if($it['value'])<img src="{{ asset($it['value']) }}" alt="">@else<i class="bi bi-person-fill"></i>@endif</span>
                            <input type="file" class="form-control form-control-sm" id="s-{{ $name }}" name="photo[{{ $name }}]" accept="image/jpeg,image/png,image/webp" style="max-width:16rem">
                        </div>
                    @elseif($it['type'] === 'enum')
                        <select class="form-select form-select-sm" style="max-width:14rem" id="s-{{ $name }}" name="s[{{ $name }}]">
                            @foreach($it['rule'] as $o)<option value="{{ $o }}" @selected((string) $it['value'] === $o)>{{ t('settings.o.' . $o) }}</option>@endforeach</select>
                    @else
                        <input class="form-control form-control-sm" id="s-{{ $name }}" name="s[{{ $name }}]" value="{{ $it['value'] }}" maxlength="{{ $it['rule'] }}" dir="auto">
                    @endif
                    <div class="small text-muted mt-1">
                        @if($it['set']){{ t('settings.changed', $it['by'], $it['at']) }} · <button class="btn btn-link btn-sm p-0 align-baseline" name="reset" value="{{ $name }}">{{ t('settings.reset') }}</button>
                        @else{{ t('settings.default') }}@endif
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    </div>
    @endforeach
    <div class="d-flex gap-2 mb-4"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ t('common.save') }}</button></div>
    </form>
    <p class="small text-muted"><i class="bi bi-lock"></i> {{ t('settings.secretsNote') }}</p>
</div>
@endsection
