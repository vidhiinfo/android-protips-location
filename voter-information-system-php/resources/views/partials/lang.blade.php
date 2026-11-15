<select class="form-select form-select-sm {{ $cls ?? '' }}" data-lang-switch title="{{ t('nav.language') }}" aria-label="{{ t('nav.language') }}">
    @foreach($languages as $code => $l)
        <option value="{{ $code }}" @selected($code === $lang)>{{ $l[0] }}{{ $code !== 'en' ? ' ('.$l[1].')' : '' }}</option>
    @endforeach
</select>
