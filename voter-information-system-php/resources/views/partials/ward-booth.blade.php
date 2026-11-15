{{-- Ward No + Booth No filters. Choosing a ward reloads the page with only that ward's booths in the Booth list
     (works without JavaScript changes); public/js/app.js also narrows the list instantly.
     params: wards, wardNo, wardMap [ward => [booths]], booths (all booths of the assembly), boothNo, showBooth (default true), small, cls --}}
@php($sm = !empty($small) ? '-sm' : '')
@php($bid = 'booth-' . substr(md5(uniqid('', true)), 0, 6))
@php($map = $wardMap ?? [])
@php($shown = !empty($wardNo) && isset($map[$wardNo]) ? $map[$wardNo] : (!empty($wardNo) ? [] : $booths))
@if(!empty($wards))
<div class="{{ $cls ?? 'col-6 col-md-2' }}"><label class="form-label {{ !empty($small) ? 'small' : '' }}">{{ t('col.wardNo') }}</label>
    <select name="wardNo" class="form-select form-select{{ $sm }}" data-autosubmit data-ward-select data-booth-select="{{ ($showBooth ?? true) ? $bid : '' }}"
            data-map='@json((object) $map)' data-all='@json(array_values($booths))'>
        <option value="">{{ t('common.all') }}</option>
        @foreach($wards as $w)<option value="{{ $w }}" @selected($w == ($wardNo ?? null))>{{ $w }}</option>@endforeach
    </select></div>
@endif
@if($showBooth ?? true)
<div class="{{ $cls ?? 'col-6 col-md-2' }}"><label class="form-label {{ !empty($small) ? 'small' : '' }}">{{ t('col.boothNo') }}</label>
    <select name="boothNo" id="{{ $bid }}" class="form-select form-select{{ $sm }}"><option value="">{{ t('common.all') }}</option>
        @foreach($shown as $b)<option value="{{ $b }}" @selected($b == ($boothNo ?? null))>{{ $b }}</option>@endforeach</select></div>
@endif
