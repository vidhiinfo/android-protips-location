{{-- Ward No + Booth No filters. Choosing a ward limits the Booth list to that ward's booths (public/js/app.js).
     params: wards, wardNo, wardMap [ward => [booths]], booths, boothNo, showBooth (default true), small, cls --}}
@php($sm = !empty($small) ? '-sm' : '')
@php($bid = 'booth-' . substr(md5(uniqid('', true)), 0, 6))
@if(!empty($wards))
<div class="{{ $cls ?? 'col-6 col-md-2' }}"><label class="form-label {{ !empty($small) ? 'small' : '' }}">{{ t('col.wardNo') }}</label>
    <select name="wardNo" class="form-select form-select{{ $sm }}" data-ward-select data-booth-select="{{ ($showBooth ?? true) ? $bid : '' }}"
            data-map='@json((object) ($wardMap ?? []))'>
        <option value="">{{ t('common.all') }}</option>
        @foreach($wards as $w)<option value="{{ $w }}" @selected($w == ($wardNo ?? null))>{{ $w }}</option>@endforeach
    </select></div>
@endif
@if($showBooth ?? true)
<div class="{{ $cls ?? 'col-6 col-md-2' }}"><label class="form-label {{ !empty($small) ? 'small' : '' }}">{{ t('col.boothNo') }}</label>
    <select name="boothNo" id="{{ $bid }}" class="form-select form-select{{ $sm }}"><option value="">{{ t('common.all') }}</option>
        @foreach($booths as $b)<option value="{{ $b }}" @selected($b == ($boothNo ?? null))>{{ $b }}</option>@endforeach</select></div>
@endif
