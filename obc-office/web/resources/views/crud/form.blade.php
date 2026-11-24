@extends('layouts.app', ['pageTitle' => ($record->exists ? 'Edit ' : 'New ').strtolower($singular), 'hideErrorSummary' => true])

@section('content')
    <form method="POST" action="{{ $record->exists ? route($routeName.'.update', $record->id) : route($routeName.'.store') }}" class="card max-w-3xl">
        @csrf
        @if ($record->exists) @method('PUT') @endif
        <div class="card-b grid gap-4 sm:grid-cols-2">
            @foreach ($fields as $name => $f)
                @include('partials.field', ['value' => $record->{$name}, 'options' => $options[$name] ?? []])
            @endforeach
        </div>
        <div class="flex items-center gap-2 border-t border-slate-100 px-4 py-3">
            <button class="btn-primary">Save</button>
            <a href="{{ route($routeName.'.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
    @if ($record->exists)
        <form method="POST" action="{{ route($routeName.'.destroy', $record->id) }}" data-confirm="Delete this {{ strtolower($singular) }}?" class="mt-4 max-w-3xl text-right">
            @csrf @method('DELETE')
            <button class="btn-danger">Delete</button>
        </form>
    @endif
@endsection
