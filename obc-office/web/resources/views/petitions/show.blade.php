@extends('layouts.app', ['pageTitle' => $petition->tracking_no])

@section('actions')
    <a href="{{ route('petitions.ack', $petition) }}" target="_blank" class="btn-secondary">🖨 Acknowledgement</a>
    @if (auth()->user()->canManage('petitions'))<a href="{{ route('petitions.edit', $petition) }}" class="btn-primary">Edit</a>@endif
@endsection

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <div class="card-h"><span>{{ $petition->subject }}</span>
                <span class="flex gap-1">@include('partials.badge', ['group' => 'priority', 'value' => $petition->priority]) @include('partials.badge', ['group' => 'petition_status', 'value' => $petition->status])</span></div>
            <dl class="card-b grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-xs text-slate-500">Petitioner</dt><dd class="font-medium">{{ $petition->petitioner_name }}</dd></div>
                <div><dt class="text-xs text-slate-500">Mobile</dt><dd class="font-medium">{{ $petition->mobile ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Area</dt><dd class="font-medium">{{ collect([$petition->district?->name, $petition->state?->name])->filter()->implode(', ') ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Category</dt><dd class="font-medium">{{ lookup_label('petition_category', $petition->category) ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Received</dt><dd class="font-medium">{{ fmt_date($petition->received_on) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Follow-up due</dt><dd class="font-medium">{{ fmt_date($petition->due_date) ?: '—' }}</dd></div>
                <div class="sm:col-span-3"><dt class="text-xs text-slate-500">Forwarded to</dt><dd class="font-medium">{{ $petition->forwarded_to ?: '—' }}</dd></div>
                <div class="sm:col-span-3"><dt class="text-xs text-slate-500">Details</dt><dd class="whitespace-pre-line">{{ $petition->details ?: '—' }}</dd></div>
                @if ($petition->attachment_path)<div><a href="{{ route('petitions.attachment', $petition) }}" class="text-brand hover:underline">📎 Download attachment</a></div>@endif
            </dl>
        </div>
        @if (auth()->user()->canManage('petitions'))
            <form method="POST" action="{{ route('petitions.updates.store', $petition) }}" class="card">
                @csrf
                <div class="card-h">Add follow-up</div>
                <div class="card-b grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">Status</label>
                        <select name="status" class="input">@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected($petition->status === $k)>{{ $l }}</option>@endforeach</select>
                    </div>
                    <div class="sm:col-span-2"><label class="label">Forwarded to (optional)</label><input name="forwarded_to" class="input"></div>
                    <div class="sm:col-span-3"><label class="label">Note</label><textarea name="note" rows="2" class="input" placeholder="Spoke to DM office; follow up next week…"></textarea></div>
                </div>
                <div class="border-t border-slate-100 px-4 py-3"><button class="btn-primary">Add update</button>
                    @if ($wa = wa_link($petition->mobile, "Your petition {$petition->tracking_no} status: ".lookup_label('petition_status', $petition->status).". — ".setting('org_name')))
                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-secondary ml-2">Send status on WhatsApp</a>
                    @endif
                </div>
            </form>
        @endif
    </div>
    <div class="card">
        <div class="card-h">Timeline</div>
        <ol class="card-b space-y-4">
            @foreach ($petition->updates as $up)
                <li class="relative border-l-2 border-slate-200 pl-4">
                    <span class="absolute top-1 -left-[5px] h-2 w-2 rounded-full bg-brand"></span>
                    <div class="flex items-center gap-2 text-xs text-slate-500">{{ fmt_datetime($up->created_at) }} @include('partials.badge', ['group' => 'petition_status', 'value' => $up->status])</div>
                    <div class="mt-1 text-sm whitespace-pre-line">{{ $up->note }}</div>
                    <div class="text-xs text-slate-400">{{ $up->user?->name }}</div>
                </li>
            @endforeach
        </ol>
    </div>
</div>
@endsection
