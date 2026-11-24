{{-- Generic field renderer: $name, $f (definition), $value, $options (array|null) --}}
@php
    $type = $f['type'] ?? 'text';
    $required = str_contains($f['rules'] ?? '', 'required');
    $val = old($name, $value);
    if ($val instanceof \Carbon\CarbonInterface) {
        $val = $type === 'datetime' ? $val->format('Y-m-d\TH:i') : $val->format('Y-m-d');
    }
    $isSelect = str_starts_with($type, 'lookup:') || in_array($type, ['state', 'user', 'select'], true);
@endphp
<div class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
    <label class="label" for="f_{{ $name }}">{{ $f['label'] }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="f_{{ $name }}" name="{{ $name }}" rows="4" class="input @error($name) input-error @enderror">{{ $val }}</textarea>
    @elseif ($isSelect)
        <select id="f_{{ $name }}" name="{{ $name }}" class="input @error($name) input-error @enderror">
            <option value="">—</option>
            @foreach ($options as $k => $label)
                <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $label }}</option>
            @endforeach
        </select>
    @else
        <input id="f_{{ $name }}" name="{{ $name }}" value="{{ $val }}"
               type="{{ ['datetime' => 'datetime-local', 'tel' => 'tel', 'email' => 'email', 'date' => 'date', 'number' => 'number'][$type] ?? 'text' }}"
               class="input @error($name) input-error @enderror" @if ($type === 'tel') inputmode="tel" @endif>
    @endif
    @error($name)<p class="err">{{ $message }}</p>@enderror
</div>
