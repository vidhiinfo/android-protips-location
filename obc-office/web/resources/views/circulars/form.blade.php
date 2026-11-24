@extends('layouts.app', ['pageTitle' => 'Issue circular'])

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ route('circulars.store') }}" class="card max-w-3xl">
    @csrf
    <div class="card-b space-y-4">
        <div><label class="label">Title *</label><input name="title" value="{{ old('title') }}" class="input" required></div>
        <div>
            <label class="label">Send to</label>
            <select name="target_state_id" class="input"><option value="">All states</option>
                @foreach ($states as $id => $n)<option value="{{ $id }}" @selected(old('target_state_id') == $id)>{{ $n }} only</option>@endforeach
            </select>
        </div>
        <div><label class="label">Message</label><textarea name="body" rows="10" class="input">{{ old('body') }}</textarea></div>
        <div><label class="label">Attachment (PDF / image / Word)</label><input type="file" name="attachment" class="input"></div>
        <p class="text-xs text-slate-500">State and district admins see it in the web portal and mobile app; you can track who has read it.</p>
    </div>
    <div class="flex gap-2 border-t border-slate-100 px-4 py-3"><button class="btn-primary">Issue</button><a href="{{ route('circulars.index') }}" class="btn-secondary">Cancel</a></div>
</form>
@endsection
