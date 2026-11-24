@extends('layouts.app', ['pageTitle' => $bio->name.' · '.$bio->reference_no])

@section('actions')
    <a href="{{ route('biodata.print', $bio) }}" target="_blank" class="btn-secondary">🖨 Print</a>
    @if (auth()->user()->canManage('biodata'))<a href="{{ route('biodata.edit', $bio) }}" class="btn-primary">Edit</a>@endif
@endsection

@section('content')
@php $canManage = auth()->user()->canManage('biodata'); @endphp
@if ($earlier->isNotEmpty())
    <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
        <b>Also received earlier:</b>
        @foreach ($earlier as $e)
            <a href="{{ route('biodata.show', $e) }}" class="ml-2 underline">{{ $e->reference_no }}</a> <span class="text-xs">({{ fmt_date($e->received_on) }}{{ $e->received_from ? ', from '.$e->received_from : '' }})</span>@if (! $loop->last),@endif
        @endforeach
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-[280px_1fr]">
    <div class="space-y-4">
        <div class="card card-b text-center">
            <div class="mx-auto h-56 w-48 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                @if ($bio->photoUrl())<img src="{{ $bio->photoUrl() }}" class="h-full w-full object-cover" alt="">
                @else<div class="flex h-full items-center justify-center text-5xl font-semibold text-slate-300">{{ $bio->initials() }}</div>@endif
            </div>
            <h2 class="mt-3 text-lg font-semibold">{{ $bio->name }}</h2>
            <p class="text-sm text-slate-500">{{ $bio->area() }}</p>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                @if ($bio->mobile)<a href="tel:{{ $bio->mobile }}" class="btn-secondary btn-sm">📞 Call</a>@endif
                @if ($wa = wa_link($bio->whatsapp ?: $bio->mobile))<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">WhatsApp</a>@endif
            </div>
            @if ($bio->photoUrl() && $canManage)
                <form method="POST" action="{{ route('biodata.photo.remove', $bio) }}" data-confirm="Remove photo?" class="mt-2">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-red-600">Remove photo</button></form>
            @endif
        </div>
        <div class="card card-b">
            <label class="label">Status</label>
            @if ($canManage)
                <form method="POST" action="{{ route('biodata.status', $bio) }}">@csrf @method('PATCH')
                    <select name="status" class="input" data-autosubmit>@foreach (\App\Models\Lookup::options('biodata_status') as $k => $l)<option value="{{ $k }}" @selected($bio->status === $k)>{{ $l }}</option>@endforeach</select>
                </form>
            @else
                @include('partials.badge', ['group' => 'biodata_status', 'value' => $bio->status])
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-h">Receipt</div>
            <dl class="card-b grid gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-xs text-slate-500">Reference no.</dt><dd class="font-mono font-semibold">{{ $bio->reference_no }}</dd></div>
                <div><dt class="text-xs text-slate-500">Received on</dt><dd class="font-medium">{{ fmt_date($bio->received_on) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Received from</dt><dd class="font-medium">{{ $bio->received_from ?: '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Via / category</dt><dd class="font-medium">{{ collect([lookup_label('biodata_source', $bio->received_via), lookup_label('biodata_category', $bio->category)])->filter()->implode(' · ') ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="card">
            <div class="card-h">Details</div>
            <dl class="card-b grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    "Father's / Husband's name" => $bio->father_name, 'Gender' => lookup_label('gender', $bio->gender),
                    'Date of birth' => $bio->dob ? fmt_date($bio->dob).' ('.$bio->dob->age.' yrs)' : null,
                    'Mobile' => $bio->mobile, 'WhatsApp' => $bio->whatsapp, 'Email' => $bio->email,
                    'Education' => lookup_label('education', $bio->education), 'Profession' => lookup_label('profession', $bio->profession),
                    'Mandal / Block' => $bio->mandal,
                ] as $label => $value)
                    <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="font-medium">{{ $value ?: '—' }}</dd></div>
                @endforeach
                @foreach ($customFields as $cf)
                    @php $v = $bio->extra[$cf->key] ?? null; @endphp
                    <div><dt class="text-xs text-slate-500">{{ $cf->label }}</dt><dd class="font-medium">{{ $cf->type === 'checkbox' ? ($v ? 'Yes' : 'No') : ($v ?: '—') }}</dd></div>
                @endforeach
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs text-slate-500">Address</dt><dd class="whitespace-pre-line">{{ $bio->address ?: '—' }}</dd></div>
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs text-slate-500">Political / social background</dt><dd class="whitespace-pre-line">{{ $bio->background ?: '—' }}</dd></div>
                @if ($bio->remarks)<div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs text-slate-500">Office remarks</dt><dd class="whitespace-pre-line">{{ $bio->remarks }}</dd></div>@endif
            </dl>
        </div>

        <div class="card">
            <div class="card-h">Biodata files ({{ $bio->documents->count() }})</div>
            <div class="card-b">
                @if ($bio->documents->isEmpty())
                    <p class="text-sm text-slate-400">No files attached.</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($bio->documents as $d)
                            <div class="flex items-center gap-3 rounded-lg p-2 ring-1 ring-slate-200">
                                <a href="{{ route('biodata.documents.show', $d) }}" target="_blank" class="flex h-14 w-12 shrink-0 items-center justify-center overflow-hidden rounded bg-slate-100 text-xl">
                                    @if ($d->isImage())<img src="{{ route('biodata.documents.show', $d) }}" class="h-full w-full object-cover" alt="">@else 📄 @endif
                                </a>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('biodata.documents.show', $d) }}" target="_blank" class="block truncate text-sm font-medium hover:text-brand">{{ $d->original_name }}</a>
                                    <div class="text-xs text-slate-400">{{ $d->sizeLabel() }} · <a href="{{ route('biodata.documents.show', [$d, 'download' => 1]) }}" class="hover:text-brand">Download</a></div>
                                </div>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('biodata.documents.destroy', $d) }}" data-confirm="Remove this file?">@csrf @method('DELETE')<button class="text-slate-400 hover:text-red-600">✕</button></form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($canManage)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('biodata.documents.store', $bio) }}" class="mt-4 flex flex-wrap items-center gap-2">@csrf
                        <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,image/*" class="input max-w-sm" required>
                        <button class="btn-secondary">Attach</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-between text-xs text-slate-500">
            <span>Recorded by {{ $bio->creator?->name ?? '—' }} on {{ fmt_datetime($bio->created_at) }}</span>
            @if ($canManage)
                <form method="POST" action="{{ route('biodata.destroy', $bio) }}" data-confirm="Delete this biodata from the register?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Delete</button></form>
            @endif
        </div>
    </div>
</div>
@endsection
