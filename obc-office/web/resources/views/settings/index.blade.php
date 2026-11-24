@extends('layouts.app', ['pageTitle' => 'Settings'])

@section('content')
@php
    $tabs = ['general' => 'General & branding', 'lists' => 'Dropdown lists', 'fields' => 'Custom fields', 'access' => 'Roles & permissions'];
    $colors = ['' => 'Default', 'gray' => 'Gray', 'blue' => 'Blue', 'green' => 'Green', 'amber' => 'Amber', 'red' => 'Red'];
@endphp
<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200">
    @foreach ($tabs as $k => $l)
        <a href="{{ route('settings.index', ['tab' => $k]) }}" class="-mb-px border-b-2 px-4 py-2 text-sm whitespace-nowrap {{ $tab === $k ? 'border-brand font-semibold text-brand' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $l }}</a>
    @endforeach
</nav>

@if ($tab === 'general')
<form method="POST" action="{{ route('settings.general') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    <div class="card">
        <div class="card-h">Organisation & branding</div>
        <div class="card-b grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2"><label class="label">Organisation name</label><input name="org_name" value="{{ old('org_name', setting('org_name')) }}" class="input" required></div>
            <div><label class="label">Short name (sidebar, app)</label><input name="org_short_name" value="{{ old('org_short_name', setting('org_short_name')) }}" class="input"></div>
            <div><label class="label">President's name / title</label><input name="president_name" value="{{ old('president_name', setting('president_name')) }}" class="input" required></div>
            <div><label class="label">Theme colour</label><div class="flex gap-2"><input type="color" name="theme_color" value="{{ old('theme_color', setting('theme_color')) }}" class="h-9 w-14 rounded border border-slate-300"><span class="pt-2 text-xs text-slate-500">Used across web and mobile apps</span></div></div>
            <div><label class="label">Footer text</label><input name="footer_text" value="{{ old('footer_text', setting('footer_text')) }}" class="input"></div>
            <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-4">
                @if (setting('logo_path'))<img src="{{ asset('storage/'.setting('logo_path')) }}" class="h-14 w-14 rounded-full bg-white object-contain ring-1 ring-slate-200" alt="">@endif
                <div><label class="label">Logo (PNG/JPG, square)</label><input type="file" name="logo" accept="image/*" class="input"></div>
                @if (setting('logo_path'))<label class="flex items-center gap-1 pt-5 text-sm"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>@endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Greeting messages</div>
        <div class="card-b grid gap-4 lg:grid-cols-2">
            <p class="text-xs text-slate-500 lg:col-span-2">Placeholders: <code>{name}</code> <code>{designation}</code> <code>{signature}</code> <code>{org}</code></p>
            <div><label class="label">Birthday message</label><textarea name="birthday_template" rows="5" class="input">{{ old('birthday_template', setting('birthday_template')) }}</textarea></div>
            <div><label class="label">Anniversary message</label><textarea name="anniversary_template" rows="5" class="input">{{ old('anniversary_template', setting('anniversary_template')) }}</textarea></div>
            <div><label class="label">Signature</label><input name="greeting_signature" value="{{ old('greeting_signature', setting('greeting_signature')) }}" class="input"></div>
            <div><label class="label">Show greetings for next (days)</label><input type="number" name="greeting_days_ahead" value="{{ old('greeting_days_ahead', setting('greeting_days_ahead')) }}" class="input" min="0" max="31"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Member records</div>
        <div class="card-b space-y-4">
            <div>
                <label class="label">Mandatory fields (name and level are always required)</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($memberFields as $k => $l)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="member_required_fields[]" value="{{ $k }}" @checked(in_array($k, (array) setting('member_required_fields')))> {{ $l }}</label>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="require_consent" value="1" class="mt-0.5" @checked(setting('require_consent'))> <span>Require recorded consent (DPDP Act)</span></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="state_entries_need_approval" value="1" class="mt-0.5" @checked(setting('state_entries_need_approval'))> <span>Entries by state/district admins need national approval</span></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="duplicate_check_mobile" value="1" class="mt-0.5" @checked(setting('duplicate_check_mobile'))> <span>Warn on duplicate mobile numbers</span></label>
            </div>
            <div class="w-48"><label class="label">Photo size (px, long side)</label><input type="number" name="photo_max_px" value="{{ setting('photo_max_px') }}" class="input" min="200" max="2000"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Biodata register</div>
        <div class="card-b space-y-4">
            <div>
                <label class="label">Mandatory fields when recording a biodata (name and received-on date are always required)</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($biodataFields as $k => $l)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="biodata_required_fields[]" value="{{ $k }}" @checked(in_array($k, (array) setting('biodata_required_fields')))> {{ $l }}</label>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="label">Reference number prefix</label><input name="biodata_prefix" value="{{ old('biodata_prefix', setting('biodata_prefix')) }}" class="input"><p class="mt-1 text-xs text-slate-500">e.g. {{ setting('biodata_prefix') }}-{{ date('Y') }}-00001</p></div>
                <div><label class="label">Max size per file (MB)</label><input type="number" name="biodata_max_file_mb" value="{{ setting('biodata_max_file_mb') }}" class="input" min="1" max="50"></div>
            </div>
            <p class="text-xs text-slate-500">Categories, "received via" options and statuses are edited under <b>Dropdown lists</b>; extra biodata fields under <b>Custom fields</b>.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Petitions & formats</div>
        <div class="card-b grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><label class="label">Tracking number prefix</label><input name="petition_prefix" value="{{ old('petition_prefix', setting('petition_prefix')) }}" class="input"><p class="mt-1 text-xs text-slate-500">e.g. {{ setting('petition_prefix') }}-{{ date('Y') }}-00001</p></div>
            <div><label class="label">Default follow-up due (days)</label><input type="number" name="petition_default_due_days" value="{{ setting('petition_default_due_days') }}" class="input"></div>
            <div>
                <label class="label">Status that means "closed"</label>
                <select name="petition_closed_status" class="input">@foreach (\App\Models\Lookup::options('petition_status', true) as $k => $l)<option value="{{ $k }}" @selected(setting('petition_closed_status') === $k)>{{ $l }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label">Date format</label>
                <select name="date_format" class="input">@foreach (['d M Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $f)<option value="{{ $f }}" @selected(setting('date_format') === $f)>{{ now()->format($f) }}</option>@endforeach</select>
            </div>
            <div>
                <label class="label">Date-time format</label>
                <select name="datetime_format" class="input">@foreach (['d M Y, h:i A', 'd/m/Y H:i', 'd-m-Y h:i A', 'Y-m-d H:i'] as $f)<option value="{{ $f }}" @selected(setting('datetime_format') === $f)>{{ now()->format($f) }}</option>@endforeach</select>
            </div>
            <div><label class="label">Rows per page</label><input type="number" name="per_page" value="{{ setting('per_page') }}" class="input" min="10" max="200"></div>
        </div>
    </div>
    <button class="btn-primary">Save settings</button>
</form>

@elseif ($tab === 'lists')
<div class="grid gap-6 lg:grid-cols-[240px_1fr]">
    <div class="card divide-y divide-slate-100 self-start">
        @foreach ($groups as $g => $l)
            <a href="{{ route('settings.index', ['tab' => 'lists', 'group' => $g]) }}" class="block px-4 py-2 text-sm {{ $group === $g ? 'bg-brand/10 font-semibold text-brand' : 'hover:bg-slate-50' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="card">
        <div class="card-h">{{ $groups[$group] ?? $group }}</div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Label (shown to users)</th><th>Code</th><th>Colour</th><th>Order</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @foreach ($lookups as $l)
                    <tr>
                        <td colspan="1">
                            <form id="lk{{ $l->id }}" method="POST" action="{{ route('settings.lookups.update', $l) }}">@csrf @method('PUT')</form>
                            <input form="lk{{ $l->id }}" name="label" value="{{ $l->label }}" class="input py-1 text-sm">
                        </td>
                        <td class="font-mono text-xs text-slate-500">{{ $l->value }} @if ($l->locked)<span title="Used by the system: can be renamed, not deleted">🔒</span>@endif</td>
                        <td><select form="lk{{ $l->id }}" name="color" class="input py-1 text-xs">@foreach ($colors as $k => $c)<option value="{{ $k }}" @selected($l->color === $k)>{{ $c }}</option>@endforeach</select></td>
                        <td><input form="lk{{ $l->id }}" name="sort" type="number" value="{{ $l->sort }}" class="input w-20 py-1 text-sm"></td>
                        <td><input form="lk{{ $l->id }}" type="checkbox" name="active" value="1" @checked($l->active) @disabled($l->locked)></td>
                        <td class="text-right whitespace-nowrap">
                            <button form="lk{{ $l->id }}" class="btn-secondary btn-sm">Save</button>
                            @unless ($l->locked)
                                <form method="POST" action="{{ route('settings.lookups.destroy', $l) }}" class="inline" data-confirm="Delete '{{ $l->label }}'? Existing records keep the code.">@csrf @method('DELETE')<button class="btn-danger btn-sm">✕</button></form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('settings.lookups.store') }}" class="grid grid-cols-2 items-end gap-2 border-t border-slate-100 p-3 sm:grid-cols-5">@csrf
            <input type="hidden" name="group" value="{{ $group }}">
            <div class="sm:col-span-2"><label class="label">New option label</label><input name="label" class="input" required></div>
            <div><label class="label">Code (optional)</label><input name="value" class="input" placeholder="auto"></div>
            <div><label class="label">Colour</label><select name="color" class="input">@foreach ($colors as $k => $c)<option value="{{ $k }}">{{ $c }}</option>@endforeach</select></div>
            <button class="btn-primary">Add option</button>
        </form>
    </div>
</div>

@elseif ($tab === 'fields')
<div class="mb-4 inline-flex rounded-lg bg-white p-1 ring-1 ring-slate-200">
    @foreach (['member' => 'Bearers & Workers', 'biodata' => 'Biodata Register'] as $k => $l)
        <a href="{{ route('settings.index', ['tab' => 'fields', 'entity' => $k]) }}" class="rounded-md px-3 py-1.5 text-sm {{ $entity === $k ? 'bg-brand text-white' : 'text-slate-600' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="card">
    <div class="card-h">Custom fields — {{ $entity === 'biodata' ? 'Biodata Register' : 'Bearers & Workers' }}</div>
    <p class="px-4 pt-3 text-sm text-slate-600">Add any information your office wants to collect (e.g. Voter ID, Assembly constituency, Blood group). Fields appear on the form, profile, print, CSV and the mobile app.</p>
    <div class="overflow-x-auto">
        <table class="table mt-3">
            <thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Options (one per line)</th><th>Order</th><th>Required</th><th>In list</th><th>Active</th><th></th></tr></thead>
            <tbody>
            @forelse ($customFields as $f)
                <tr>
                    <td><form id="cf{{ $f->id }}" method="POST" action="{{ route('settings.fields.update', $f) }}">@csrf @method('PUT')</form>
                        <input form="cf{{ $f->id }}" name="label" value="{{ $f->label }}" class="input py-1 text-sm"></td>
                    <td class="font-mono text-xs text-slate-500">{{ $f->key }}</td>
                    <td><select form="cf{{ $f->id }}" name="type" class="input py-1 text-xs">@foreach (\App\Models\CustomField::TYPES as $k => $t)<option value="{{ $k }}" @selected($f->type === $k)>{{ $t }}</option>@endforeach</select></td>
                    <td><textarea form="cf{{ $f->id }}" name="options" rows="2" class="input py-1 text-xs">{{ $f->options }}</textarea></td>
                    <td><input form="cf{{ $f->id }}" name="sort" type="number" value="{{ $f->sort }}" class="input w-16 py-1 text-sm"></td>
                    <td><input form="cf{{ $f->id }}" type="checkbox" name="required" value="1" @checked($f->required)></td>
                    <td><input form="cf{{ $f->id }}" type="checkbox" name="show_in_list" value="1" @checked($f->show_in_list)></td>
                    <td><input form="cf{{ $f->id }}" type="checkbox" name="active" value="1" @checked($f->active)></td>
                    <td class="whitespace-nowrap text-right">
                        <button form="cf{{ $f->id }}" class="btn-secondary btn-sm">Save</button>
                        <form method="POST" action="{{ route('settings.fields.destroy', $f) }}" class="inline" data-confirm="Remove field? Stored values are kept and come back if re-added.">@csrf @method('DELETE')<button class="btn-danger btn-sm">✕</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="99" class="py-6 text-center text-sm text-slate-400">No custom fields yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <form method="POST" action="{{ route('settings.fields.store') }}" class="grid grid-cols-2 items-end gap-2 border-t border-slate-100 p-3 lg:grid-cols-6">@csrf
        <input type="hidden" name="entity" value="{{ $entity }}">
        <div class="lg:col-span-2"><label class="label">Label</label><input name="label" class="input" required placeholder="e.g. Voter ID"></div>
        <div><label class="label">Type</label><select name="type" class="input">@foreach (\App\Models\CustomField::TYPES as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
        <div class="lg:col-span-2"><label class="label">Dropdown options (one per line)</label><textarea name="options" rows="1" class="input"></textarea></div>
        <div class="flex flex-col gap-1 text-xs">
            <label><input type="checkbox" name="required" value="1"> Required</label>
            <label><input type="checkbox" name="show_in_list" value="1"> Show in list</label>
            <input type="hidden" name="active" value="1">
        </div>
        <button class="btn-primary col-span-2 lg:col-span-6 lg:w-40">Add field</button>
    </form>
</div>

@elseif ($tab === 'access')
<form method="POST" action="{{ route('settings.access') }}" class="card">
    @csrf
    <div class="card-h">Modules & role permissions</div>
    <p class="px-4 pt-3 text-sm text-slate-600"><b>View</b> = can see; <b>Manage</b> = can add / edit / delete. Super Admin always has full access. State and district users are always limited to their own area's data. Switching a module off hides it for everyone.</p>
    <div class="overflow-x-auto">
        <table class="table mt-3">
            <thead><tr><th>Module</th><th class="text-center">Enabled</th>@foreach ($roles as $r => $label)<th>{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($modules as $key => $m)
                <tr>
                    <td class="font-medium whitespace-nowrap">{{ $m['icon'] }} {{ $m['label'] }}<div class="text-xs font-normal text-slate-400">{{ $m['group'] }}</div></td>
                    <td class="text-center"><input type="checkbox" name="enabled[{{ $key }}]" value="1" @checked(setting('modules_enabled')[$key] ?? true) @disabled(in_array($key, ['settings', 'dashboard']))>
                        @if (in_array($key, ['settings', 'dashboard']))<input type="hidden" name="enabled[{{ $key }}]" value="1">@endif</td>
                    @foreach ($roles as $r => $label)
                        @php $cur = setting('permissions')[$r][$key] ?? 'none'; @endphp
                        <td>
                            <select name="perm[{{ $r }}][{{ $key }}]" class="input py-1 text-xs {{ $cur === 'manage' ? 'text-green-700' : ($cur === 'view' ? 'text-blue-700' : 'text-slate-400') }}">
                                @foreach ($m['levels'] as $lvl)<option value="{{ $lvl }}" @selected($cur === $lvl)>{{ ucfirst($lvl) }}</option>@endforeach
                            </select>
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-4 py-3"><button class="btn-primary">Save permissions</button></div>
</form>
@endif
@endsection
