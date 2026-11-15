@extends('layouts.app')
@section('title', t('account.changePassword'))
@section('content')
<div class="container" style="max-width:500px">
    <h4 class="mb-3">{{ t('account.changePassword') }}</h4>
    @if(!empty($error))<div class="alert alert-danger py-2">{{ t($error) }}</div>@endif
    <form method="post" action="{{ url('/account/password') }}" class="card card-body">@csrf
        <div class="mb-3"><label class="form-label" for="current">{{ t('account.current') }}</label><input id="current" name="current" type="password" class="form-control" required autocomplete="current-password"></div>
        <div class="mb-3"><label class="form-label" for="np">{{ t('account.new') }}</label><input id="np" name="newPassword" type="password" class="form-control" required minlength="8" autocomplete="new-password">
            <div class="form-text">{{ t('validation.password') }}</div></div>
        <div class="mb-3"><label class="form-label" for="cf">{{ t('account.confirm') }}</label><input id="cf" name="confirm" type="password" class="form-control" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-primary">{{ t('common.save') }}</button>
    </form>
</div>
@endsection
