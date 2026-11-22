@extends('layouts.app')
@section('title', t('settings.title'))
@section('content')
<div class="container px-3" style="max-width:820px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-toggles"></i> {{ t('settings.title') }}</h4>
    <p class="small text-muted">{{ t('settings.intro') }}</p>
    <form method="post" action="{{ url('/admin/settings') }}" class="card">@csrf
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-chat-dots fs-3 text-primary"></i>
                <div class="me-auto">
                    <div class="fw-semibold">{{ t('settings.msgTitle') }}
                        <span class="badge {{ $messaging ? 'text-bg-success' : 'text-bg-secondary' }} ms-1">{{ $messaging ? t('settings.on') : t('settings.off') }}</span></div>
                    <div class="small text-muted">{{ t('settings.msgHelp') }}</div>
                    <div class="small text-muted mt-1">@if($messagingSet && $changedBy){{ t('settings.changed', $changedBy->updated_by, substr((string) $changedBy->updated_at, 0, 16)) }}@else{{ t('settings.fromEnv', $envDefault ? t('settings.on') : t('settings.off')) }}@endif</div>
                </div>
            </div>
            <div class="btn-group mt-3" role="group">
                <input type="radio" class="btn-check" name="messaging" id="msgOn" value="1" @checked($messaging)>
                <label class="btn btn-outline-success" for="msgOn"><i class="bi bi-toggle-on"></i> {{ t('settings.turnOn') }}</label>
                <input type="radio" class="btn-check" name="messaging" id="msgOff" value="0" @checked(!$messaging)>
                <label class="btn btn-outline-secondary" for="msgOff"><i class="bi bi-toggle-off"></i> {{ t('settings.turnOff') }}</label>
            </div>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary">{{ t('common.save') }}</button></div>
    </form>
</div>
@endsection
