{{-- input: name, label (key), cls, type, local (dir=auto), value --}}
@php($err = isset($errors) ? $errors->first($name) : null)
<div class="{{ $cls ?? 'col-md-6' }}">
    <label class="form-label" for="{{ $name }}">{{ t($label) }}</label>
    <input type="{{ $type ?? 'text' }}" id="{{ $name }}" name="{{ $name }}" value="{{ $value ?? '' }}" class="form-control {{ $err ? 'is-invalid' : '' }}"
           @if(!empty($local)) dir="auto" @endif @if(!empty($readonly)) readonly @endif @if(!empty($list)) list="{{ $list }}" @endif>
    @if($err)<div class="invalid-feedback">{{ $err }}</div>@endif
</div>
