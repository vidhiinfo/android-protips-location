@extends('layouts.app', ['pageTitle' => 'Birthday & Anniversary Greetings'])

@section('content')
<form class="no-print mb-4 flex items-end gap-2">
    <div><label class="label">Show next</label>
        <select name="days" class="input" data-autosubmit>
            @foreach ([0 => 'Today only', 1 => 'Today & tomorrow', 7 => '7 days', 15 => '15 days', 30 => '30 days'] as $d => $l)
                <option value="{{ $d }}" @selected($days == $d)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <p class="pb-2 text-xs text-slate-500">Message text is configured in Settings → General. Clicking WhatsApp opens a pre-filled chat.</p>
</form>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($items as $i => $g)
        <div class="card flex flex-col">
            <div class="card-b flex-1">
                <div class="flex items-center gap-3">
                    <span class="avatar h-12 w-12">@if ($g['photo_url'])<img src="{{ $g['photo_url'] }}" class="h-full w-full object-cover" alt="">@else {{ mb_substr($g['name'], 0, 1) }} @endif</span>
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ $g['name'] }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $g['designation'] }} {{ $g['area'] ? '· '.$g['area'] : '' }}</div>
                    </div>
                    <span class="ml-auto {{ $g['in_days'] === 0 ? 'badge-green' : 'badge-gray' }}">
                        {{ $g['kind'] === 'birthday' ? '🎂' : '💐' }} {{ $g['in_days'] === 0 ? 'Today' : ($g['in_days'] === 1 ? 'Tomorrow' : $g['date']) }}
                    </span>
                </div>
                <textarea id="msg{{ $i }}" rows="4" class="input mt-3 text-xs">{{ $g['message'] }}</textarea>
            </div>
            <div class="flex gap-2 border-t border-slate-100 px-4 py-2">
                @if ($g['whatsapp_url'])<a href="{{ $g['whatsapp_url'] }}" target="_blank" rel="noopener" class="btn-primary btn-sm">WhatsApp</a>@endif
                @if ($g['mobile'])<a href="tel:{{ $g['mobile'] }}" class="btn-secondary btn-sm">Call</a>@endif
                <button type="button" class="btn-secondary btn-sm" data-copy="#msg{{ $i }}">Copy text</button>
            </div>
        </div>
    @empty
        <div class="card card-b col-span-full py-10 text-center text-slate-400">No birthdays or anniversaries in this period.</div>
    @endforelse
</div>
@endsection
