{{-- Display a value according to its field definition --}}
@php $type = $f['type'] ?? 'text'; $v = $record->{$name}; @endphp
@if (str_starts_with($type, 'lookup:'))
    @include('partials.badge', ['group' => substr($type, 7), 'value' => $v])
@elseif ($type === 'date')
    {{ fmt_date($v) }}
@elseif ($type === 'datetime')
    {{ fmt_datetime($v) }}
@elseif (in_array($type, ['state', 'user', 'select'], true))
    {{ $options[$name][$v] ?? '' }}
@elseif ($type === 'tel' && $v)
    <a href="tel:{{ $v }}" class="text-brand hover:underline">{{ $v }}</a>
@else
    {{ \Illuminate\Support\Str::limit((string) $v, 60) }}
@endif
