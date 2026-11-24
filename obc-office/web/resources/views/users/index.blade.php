@extends('layouts.app', ['pageTitle' => 'Users & Access'])

@section('actions')<a href="{{ route('users.create') }}" class="btn-primary">+ Add user</a>@endsection

@section('content')
<form class="mb-4 flex flex-wrap items-end gap-2">
    <div class="min-w-48 flex-1"><label class="label">Search</label><input name="q" value="{{ request('q') }}" class="input"></div>
    <div><label class="label">Role</label>
        <select name="role" class="input" data-autosubmit><option value="">All</option>
            @foreach (\App\Models\User::ROLES as $k => $l)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
</form>
<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Jurisdiction</th><th>Last login</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td class="font-medium">{{ $u->name }}<div class="text-xs text-slate-500">{{ $u->mobile }}</div></td>
                <td class="font-mono text-xs">{{ $u->username }}</td>
                <td class="text-sm">{{ $u->roleLabel() }}</td>
                <td class="text-sm">{{ $u->jurisdictionLabel() }}</td>
                <td class="text-xs text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td>{!! $u->active ? '<span class="badge-green">Active</span>' : '<span class="badge-gray">Disabled</span>' !!}</td>
                <td class="text-right"><a href="{{ route('users.edit', $u) }}" class="btn-secondary btn-sm">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
<p class="mt-4 text-xs text-slate-500">What each role can do is configured in <a href="{{ route('settings.index', ['tab' => 'access']) }}" class="text-brand hover:underline">Settings → Roles & permissions</a>. State and district admins only ever see data from their own area.</p>
@endsection
