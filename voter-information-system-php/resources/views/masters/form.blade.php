@extends('layouts.app')
@section('title', t($d['one']))
@section('content')
<div class="container" style="max-width:900px">
    <h4 class="mb-3">{{ t($d['one']) }}</h4>
    <form method="post" action="{{ url('/masters/'.$d['type']) }}" class="card" novalidate>@csrf
        <input type="hidden" name="id" value="{{ $item->id }}">
        <div class="card-body row g-3">
        @foreach($d['fields'] as $name => $f)
            @php($err = isset($errors) ? $errors->first($name) : null)
            @php($val = $item->$name instanceof \Carbon\Carbon ? $item->$name->format('Y-m-d') : $item->$name)
            @if($f[1] === 'select')
                <div class="col-md-6"><label class="form-label">{{ t($f[0]) }}</label>
                    <select name="{{ $name }}" class="form-select {{ $err ? 'is-invalid' : '' }}">
                        @unless(array_key_exists('', $f[3]()))<option value="">--</option>@endunless
                        @foreach($f[3]() as $k => $label)<option value="{{ $k }}" @selected((string) $k === (string) $val)>{{ $label }}</option>@endforeach
                    </select>@if($err)<div class="invalid-feedback">{{ $err }}</div>@endif</div>
            @elseif($f[1] === 'checkbox')
                <div class="col-md-6 d-flex align-items-end"><div class="form-check">
                    <input type="checkbox" class="form-check-input" id="{{ $name }}" name="{{ $name }}" value="1" @checked($val)>
                    <label class="form-check-label" for="{{ $name }}">{{ t($f[0]) }}</label></div></div>
            @else
                @include('partials.input', ['name' => $name, 'label' => $f[0], 'cls' => 'col-md-6', 'value' => $val,
                    'type' => ['number' => 'number', 'date' => 'date'][$f[1]] ?? 'text', 'local' => $f[1] === 'local'])
            @endif
        @endforeach
        </div>
        <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ t('common.save') }}</button>
            <a class="btn btn-outline-secondary" href="{{ url('/masters/'.$d['type']) }}">{{ t('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
