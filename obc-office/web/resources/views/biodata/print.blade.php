<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Biodata — {{ $bio->name }}</title>
@vite(['resources/css/app.css'])<style>:root { --color-brand: {{ setting('theme_color') }}; } td { vertical-align: top; }</style></head>
<body class="bg-white p-8 text-slate-900" onload="window.print()">
<div class="mx-auto max-w-3xl">
    <div class="flex items-center gap-4 border-b-2 border-brand pb-3">
        @if (setting('logo_path'))<img src="{{ branding_image_url('logo') }}" class="h-14 w-14 object-contain" alt="">@endif
        <div class="flex-1"><div class="text-lg font-bold">{{ setting('org_name') }}</div><div class="text-sm text-slate-600">Biodata</div></div>
        <div class="text-right text-xs text-slate-500">{{ $bio->reference_no }}<br>Received {{ fmt_date($bio->received_on) }}</div>
    </div>
    <div class="mt-6 flex gap-6">
        <table class="flex-1 text-sm">
            @foreach ([
                'Name' => $bio->name, "Father's / Husband's name" => $bio->father_name, 'Gender' => lookup_label('gender', $bio->gender),
                'Date of birth' => $bio->dob ? fmt_date($bio->dob).' ('.$bio->dob->age.' yrs)' : null,
                'Mobile' => collect([$bio->mobile, $bio->whatsapp ? 'WhatsApp: '.$bio->whatsapp : null])->filter()->implode(' · '),
                'Email' => $bio->email, 'Area' => $bio->area(), 'Address' => $bio->address,
                'Education' => lookup_label('education', $bio->education), 'Profession' => lookup_label('profession', $bio->profession),
            ] + $customFields->mapWithKeys(fn ($cf) => [$cf->label => $cf->type === 'checkbox' ? (($bio->extra[$cf->key] ?? null) ? 'Yes' : 'No') : ($bio->extra[$cf->key] ?? null)])->all() as $k => $v)
                <tr><td class="w-48 py-1.5 pr-3 text-slate-500">{{ $k }}</td><td class="py-1.5 font-medium whitespace-pre-line">{{ $v ?: '—' }}</td></tr>
            @endforeach
        </table>
        <div class="h-44 w-36 shrink-0 overflow-hidden border border-slate-300 bg-slate-50">
            @if ($bio->photoUrl())<img src="{{ $bio->photoUrl() }}" class="h-full w-full object-cover" alt="">@endif
        </div>
    </div>
    <h3 class="mt-6 border-b border-slate-300 pb-1 text-sm font-semibold uppercase">Political / social background</h3>
    <p class="mt-2 text-sm whitespace-pre-line">{{ $bio->background ?: '—' }}</p>
    <h3 class="mt-6 border-b border-slate-300 pb-1 text-sm font-semibold uppercase">Receipt</h3>
    <p class="mt-2 text-sm">Received from <b>{{ $bio->received_from ?: '—' }}</b>{{ $bio->received_via ? ' via '.lookup_label('biodata_source', $bio->received_via) : '' }}{{ $bio->category ? ' · '.lookup_label('biodata_category', $bio->category) : '' }}</p>
    @if ($bio->remarks)<h3 class="mt-6 border-b border-slate-300 pb-1 text-sm font-semibold uppercase">Office remarks</h3><p class="mt-2 text-sm whitespace-pre-line">{{ $bio->remarks }}</p>@endif
    <p class="mt-10 text-center text-xs text-slate-400">{{ setting('footer_text') }}</p>
</div>
</body></html>
