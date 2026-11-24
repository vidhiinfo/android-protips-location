@extends('layouts.app', ['pageTitle' => $user->exists ? 'Edit user' : 'Add user'])

@section('content')
@php $districtJson = $districtsByState->map(fn ($ds) => $ds->map(fn ($d) => [$d->id, $d->name])->values()); @endphp
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card max-w-2xl">
    @csrf @if ($user->exists) @method('PUT') @endif
    <div class="card-b grid gap-4 sm:grid-cols-2">
        <div><label class="label">Full name *</label><input name="name" value="{{ old('name', $user->name) }}" class="input" required></div>
        <div><label class="label">Username *</label><input name="username" value="{{ old('username', $user->username) }}" class="input" required autocomplete="off"></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', str_ends_with((string) $user->email, '@users.local') ? '' : $user->email) }}" class="input"></div>
        <div><label class="label">Mobile</label><input name="mobile" value="{{ old('mobile', $user->mobile) }}" class="input"></div>
        <div class="sm:col-span-2">
            <label class="label">Role *</label>
            <select name="role" class="input">@foreach (\App\Models\User::ROLES as $k => $l)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div>
            <label class="label">State (for state / district admins)</label>
            <select name="state_id" class="input" data-state-select="#u_district"><option value="">—</option>
                @foreach ($states as $s)<option value="{{ $s->id }}" @selected((string) old('state_id', $user->state_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="label">District (for district admins)</label><select name="district_id" id="u_district" class="input" data-districts='@json($districtJson)' data-value="{{ old('district_id', $user->district_id) }}"></select></div>
        <div><label class="label">{{ $user->exists ? 'New password (leave blank to keep)' : 'Password *' }}</label><input type="password" name="password" class="input" autocomplete="new-password"></div>
        <label class="flex items-center gap-2 pt-6 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> Account active</label>
        <p class="text-xs text-slate-500 sm:col-span-2">Passwords need at least 8 characters with letters and numbers. Changing a password signs the user out of the mobile app.</p>
    </div>
    <div class="flex gap-2 border-t border-slate-100 px-4 py-3"><button class="btn-primary">Save</button><a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a></div>
</form>
@endsection
