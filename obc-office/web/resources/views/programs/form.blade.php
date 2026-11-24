@extends('layouts.app', ['pageTitle' => $program->exists ? 'Edit programme' : 'New programme'])

@section('content')
<form method="POST" action="{{ $program->exists ? route('programs.update', $program) : route('programs.store') }}" class="card max-w-2xl">
    @csrf @if ($program->exists) @method('PUT') @endif
    <div class="card-b grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="label">Name *</label><input name="name" value="{{ old('name', $program->name) }}" class="input" required></div>
        <div><label class="label">Start date</label><input type="date" name="start_date" value="{{ old('start_date', $program->start_date?->format('Y-m-d')) }}" class="input"></div>
        <div><label class="label">End date</label><input type="date" name="end_date" value="{{ old('end_date', $program->end_date?->format('Y-m-d')) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Description / instructions to states</label><textarea name="description" rows="6" class="input">{{ old('description', $program->description) }}</textarea></div>
    </div>
    <div class="flex gap-2 border-t border-slate-100 px-4 py-3"><button class="btn-primary">Save</button><a href="{{ route('programs.index') }}" class="btn-secondary">Cancel</a></div>
</form>
@if ($program->exists)
    <form method="POST" action="{{ route('programs.destroy', $program) }}" data-confirm="Delete programme and all its reports?" class="mt-4 max-w-2xl text-right">@csrf @method('DELETE')<button class="btn-danger">Delete</button></form>
@endif
@endsection
