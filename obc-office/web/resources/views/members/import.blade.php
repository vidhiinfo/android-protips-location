@extends('layouts.app', ['pageTitle' => 'Bulk Import / Export'])

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card">
        <div class="card-h">Import members from Excel / CSV</div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('import.run') }}" class="card-b space-y-4">
            @csrf
            <ol class="list-inside list-decimal space-y-1 text-sm text-slate-600">
                <li><a href="{{ route('import.template') }}" class="font-medium text-brand hover:underline">Download the template</a> (opens in Excel).</li>
                <li>Fill one person per row. State, district and designation names must match the masters.</li>
                <li>In Excel use <i>File → Save As → CSV UTF-8</i>, then upload below.</li>
            </ol>
            <div><label class="label">CSV file</label><input type="file" name="file" accept=".csv,text/csv" class="input" required></div>
            <div>
                <label class="label">If the mobile number already exists</label>
                <select name="mode" class="input">
                    <option value="skip">Skip that row</option>
                    <option value="update">Update the existing record</option>
                </select>
            </div>
            @if (setting('require_consent'))
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Consent is mandatory (Settings). Rows need <code>consent = yes</code>, otherwise they are rejected.</p>
            @endif
            <button class="btn-primary">Upload & import</button>
        </form>
    </div>

    <div class="space-y-6">
        @if ($r = session('import_result'))
            <div class="card">
                <div class="card-h">Import result</div>
                <div class="card-b">
                    <div class="grid grid-cols-4 gap-2 text-center text-sm">
                        <div class="rounded-lg bg-green-50 p-2"><b class="block text-xl text-green-700">{{ $r['created'] }}</b>Created</div>
                        <div class="rounded-lg bg-blue-50 p-2"><b class="block text-xl text-blue-700">{{ $r['updated'] }}</b>Updated</div>
                        <div class="rounded-lg bg-slate-50 p-2"><b class="block text-xl">{{ $r['skipped'] }}</b>Skipped</div>
                        <div class="rounded-lg bg-red-50 p-2"><b class="block text-xl text-red-700">{{ count($r['errors']) }}</b>Errors</div>
                    </div>
                    @if ($r['errors'])
                        <ul class="mt-3 max-h-72 list-inside list-disc overflow-auto text-xs text-red-700">
                            @foreach ($r['errors'] as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
        <div class="card">
            <div class="card-h">Columns</div>
            <div class="card-b flex flex-wrap gap-1.5">
                @foreach ($columns as $c)<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $c }}</code>@endforeach
                <p class="mt-2 w-full text-xs text-slate-500">Dates: YYYY-MM-DD or DD/MM/YYYY. Level / type / gender use the codes from Settings → Dropdown lists. New districts are created automatically when imported by the national office.</p>
            </div>
        </div>
        <div class="card card-b">
            <div class="font-semibold">Export</div>
            <p class="mt-1 text-sm text-slate-600">Use <b>Export CSV</b> on the Bearers & Workers page; it exports exactly the filtered list. Every export is recorded in the audit log.</p>
            <a href="{{ route('members.index') }}" class="btn-secondary mt-3">Go to members →</a>
        </div>
    </div>
</div>
@endsection
