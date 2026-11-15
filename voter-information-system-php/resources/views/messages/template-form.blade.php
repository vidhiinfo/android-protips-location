@extends('layouts.app')
@section('title', t('msg.template'))
@section('content')
<div class="container" style="max-width:950px">
    <h4 class="mb-3">{{ t('msg.template') }}</h4>
    <form method="post" action="{{ url('/messages/templates') }}" class="card" novalidate>@csrf
        <input type="hidden" name="id" value="{{ $item->id }}">
        <div class="card-body row g-3">
            @include('partials.input', ['name' => 'name', 'label' => 'msg.templateName', 'cls' => 'col-md-6', 'value' => $item->name, 'local' => true])
            <div class="col-md-3"><label class="form-label">{{ t('msg.channel') }}</label><select name="channel" class="form-select">@foreach($channels as $c)<option value="{{ $c }}" @selected($c === $item->channel)>{{ t('msg.channel.'.$c) }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">{{ t('nav.language') }}</label><select name="language" class="form-select">@foreach($languages as $code => $l)<option value="{{ $code }}" @selected($code === $item->language)>{{ $l[0] }}</option>@endforeach</select></div>
            @php($be = isset($errors) ? $errors->first('body') : null)
            <div class="col-12"><label class="form-label">{{ t('msg.body') }}</label>
                <textarea name="body" rows="5" class="form-control {{ $be ? 'is-invalid' : '' }}" dir="auto" maxlength="1000">{{ $item->body }}</textarea>
                @if($be)<div class="invalid-feedback">{{ $be }}</div>@endif
                <div class="form-text">{{ t('msg.placeholders') }} @foreach($placeholders as $p)<code>{{ '{'.$p.'}' }}</code> @endforeach</div></div>
            @include('partials.input', ['name' => 'provider_template_id', 'label' => 'msg.providerTemplateId', 'cls' => 'col-md-6', 'value' => $item->provider_template_id])
            @include('partials.input', ['name' => 'params', 'label' => 'msg.params', 'cls' => 'col-md-6', 'value' => $item->params])
            <div class="col-12 form-text mt-0">{{ t('msg.providerHelp') }}</div>
            <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" id="active" name="active" value="1" @checked($item->active)><label class="form-check-label" for="active">{{ t('admin.active') }}</label></div></div>
        </div>
        <div class="card-footer d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ t('common.save') }}</button>
            <a class="btn btn-outline-secondary" href="{{ url('/messages/templates') }}">{{ t('common.cancel') }}</a></div>
    </form>
</div>
@endsection
