<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Acknowledgement {{ $petition->tracking_no }}</title>
@vite(['resources/css/app.css'])<style>:root { --color-brand: {{ setting('theme_color') }}; }</style></head>
<body class="bg-white p-8 text-slate-900" onload="window.print()">
<div class="mx-auto max-w-xl rounded-xl border-2 border-slate-300 p-6">
    <div class="flex items-center gap-4 border-b-2 border-brand pb-3">
        @if (setting('logo_path'))<img src="{{ branding_image_url('logo') }}" class="h-14 w-14 object-contain" alt="">@endif
        <div><div class="text-lg font-bold">{{ setting('org_name') }}</div><div class="text-sm text-slate-600">Office of the {{ setting('president_name') }}</div></div>
    </div>
    <h1 class="mt-4 text-center text-base font-semibold tracking-wide uppercase">Petition Acknowledgement</h1>
    <table class="mt-4 w-full text-sm">
        @foreach (['Tracking number' => $petition->tracking_no, 'Received on' => fmt_date($petition->received_on), 'Petitioner' => $petition->petitioner_name,
            'Mobile' => $petition->mobile, 'Area' => collect([$petition->district?->name, $petition->state?->name])->filter()->implode(', '), 'Subject' => $petition->subject] as $k => $v)
            <tr><td class="w-40 py-1.5 text-slate-500">{{ $k }}</td><td class="py-1.5 font-medium">{{ $v ?: '—' }}</td></tr>
        @endforeach
    </table>
    <p class="mt-4 text-xs text-slate-600">Please quote the tracking number for any follow-up regarding this petition.</p>
    <div class="mt-10 text-right text-sm">Authorised signatory</div>
</div>
</body></html>
