@extends('layouts.app', ['pageTitle' => $member->name])

@section('actions')
    @if (auth()->user()->canManage('members'))
        <a href="{{ route('members.edit', $member) }}" class="btn-primary">Edit</a>
    @endif
@endsection

@section('content')
@php $u = auth()->user(); @endphp
@if ($member->status === 'proposed')
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
        <span class="flex-1">This record is <b>awaiting approval</b> by the national office.</span>
        @if ($u->isNational() && $u->canManage('members'))
            <form method="POST" action="{{ route('members.approve', $member) }}" class="flex gap-2">@csrf
                <button name="decision" value="approve" class="btn-primary btn-sm">Approve</button>
                <button name="decision" value="reject" class="btn-danger btn-sm">Reject</button>
            </form>
        @endif
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-[280px_1fr]">
    <div class="card card-b text-center">
        <div class="mx-auto h-56 w-48 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
            @if ($member->photo_url)
                <img src="{{ $member->photo_url }}" class="h-full w-full object-cover" alt="{{ $member->name }}">
            @else
                <div class="flex h-full items-center justify-center text-5xl font-semibold text-slate-300">{{ $member->initials() }}</div>
            @endif
        </div>
        <h2 class="mt-3 text-lg font-semibold">{{ $member->name }}</h2>
        <p class="text-sm font-medium text-brand">{{ $member->designation?->title }}</p>
        <p class="text-sm text-slate-500">{{ lookup_label('level', $member->level) }} · {{ $member->area() }}</p>
        <div class="mt-3 flex flex-wrap justify-center gap-2">
            @if ($member->mobile)<a href="tel:{{ $member->mobile }}" class="btn-secondary btn-sm">📞 Call</a>@endif
            @if ($wa = wa_link($member->whatsapp ?: $member->mobile))<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">WhatsApp</a>@endif
        </div>
        @if ($member->photo_url && $u->canManage('members'))
            <form method="POST" action="{{ route('members.photo.remove', $member) }}" data-confirm="Remove photo?" class="mt-3">@csrf @method('DELETE')
                <button class="text-xs text-slate-400 hover:text-red-600">Remove photo</button>
            </form>
        @endif
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-h">Details <span>@include('partials.badge', ['group' => 'member_status', 'value' => $member->status])</span></div>
            <dl class="card-b grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'Type' => lookup_label('member_type', $member->type),
                    "Father's / Husband's name" => $member->father_name,
                    'Gender' => lookup_label('gender', $member->gender),
                    'Date of birth' => $member->dob ? fmt_date($member->dob).' ('.$member->dob->age.' yrs)' : null,
                    'Mobile' => $member->mobile, 'WhatsApp' => $member->whatsapp, 'Email' => $member->email,
                    'Profession' => lookup_label('profession', $member->profession),
                    'Education' => lookup_label('education', $member->education),
                    'Skills' => $member->skills, 'Mandal / Block' => $member->mandal,
                    'Appointed on' => fmt_date($member->appointed_on), 'Tenure ends' => fmt_date($member->tenure_end),
                    'Facebook' => $member->facebook, 'X / Twitter' => $member->twitter,
                ] as $label => $value)
                    <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="font-medium text-slate-800">{{ $value ?: '—' }}</dd></div>
                @endforeach
                @foreach ($customFields as $cf)
                    @php $v = $member->extra[$cf->key] ?? null; @endphp
                    <div><dt class="text-xs text-slate-500">{{ $cf->label }}</dt><dd class="font-medium">{{ $cf->type === 'checkbox' ? ($v ? 'Yes' : 'No') : ($v ?: '—') }}</dd></div>
                @endforeach
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs text-slate-500">Address</dt><dd class="font-medium whitespace-pre-line">{{ $member->address ?: '—' }}</dd></div>
                @if ($member->notes)<div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs text-slate-500">Notes</dt><dd class="whitespace-pre-line">{{ $member->notes }}</dd></div>@endif
            </dl>
        </div>
        <div class="card card-b grid gap-3 text-xs text-slate-500 sm:grid-cols-3">
            <div>Profile completeness: <b class="text-slate-800">{{ $member->completeness() }}%</b></div>
            <div>Consent: <b class="{{ $member->consent ? 'text-green-700' : 'text-red-600' }}">{{ $member->consent ? 'Given '.fmt_date($member->consent_at) : 'Not recorded' }}</b></div>
            <div>Added by {{ $member->creator?->name ?? 'system' }} · {{ fmt_date($member->created_at) }}</div>
        </div>
        @if ($u->canManage('members'))
            <form method="POST" action="{{ route('members.destroy', $member) }}" data-confirm="Remove {{ $member->name }}? The record will be archived." class="text-right">
                @csrf @method('DELETE')
                <button class="btn-danger">Delete record</button>
            </form>
        @endif
    </div>
</div>
@endsection
