@extends('layouts.app')
@section('title', t('gr.title'))
@section('content')
<div class="container" style="max-width:860px">
    @include('partials.flash')
    <h4 class="mb-3"><i class="bi bi-megaphone"></i> {{ $g->exists ? t('gr.edit') . ' #' . $g->id : t('gr.add') }}</h4>
    <form method="post" action="{{ url($g->exists ? '/grievances/'.$g->id : '/grievances') }}" class="card card-body">@csrf
        <input type="hidden" name="voter_id" value="{{ old('voter_id', $g->voter_id) }}" data-pick-value>
        @if($g->voter_id)<div class="alert alert-light border py-2 small"><i class="bi bi-person"></i> {{ t('gr.linkedVoter') }}: <a href="{{ url('/voters/'.$g->voter_id) }}">{{ $g->name }}</a></div>
        @elseif(!$g->exists)
        <div class="border rounded p-2 bg-light mb-3" data-voter-pick data-url="{{ url('/voters/pick') }}" data-key="id" data-for="grievance" data-name-input="#gr-name"
            data-searching="{{ t('worker.searching') }}" data-none="{{ t('worker.noMatch') }}" data-taken="" data-booth-label="{{ t('col.boothNo') }}" data-sr-label="{{ t('list.col.sr') }}">
            <label class="form-label fw-semibold mb-1" for="voter-q"><i class="bi bi-person-vcard"></i> {{ t('gr.pickVoter') }}</label>
            <div data-picked class="d-none alert alert-success py-2 mb-2 d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill"></i>
                <div class="me-auto"><span class="small">{{ t('gr.linkedVoter') }}:</span> <b data-picked-name></b> <span class="small" data-picked-info></span></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-unpick>{{ t('worker.change') }}</button></div>
            <div data-search><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="voter-q" class="form-control" autocomplete="off" placeholder="{{ t('worker.searchPh') }}" maxlength="60" data-scan="input"></div>
                <div class="list-group mt-1" data-results></div><div class="form-text">{{ t('gr.pickVoterHint') }}</div></div>
        </div>
        @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ t('gr.complainant') }} *</label><input id="gr-name" name="name" value="{{ old('name', $g->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="120"></div>
            <div class="col-md-6"><label class="form-label">{{ t('col.mobile') }}</label><input name="mobile" value="{{ old('mobile', $g->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" inputmode="tel" maxlength="10">@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">{{ t('col.assemblyNo') }} *</label><select name="assembly_no" class="form-select" data-pick-assembly @disabled($me->scoped())>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == old('assembly_no', $g->assembly_no))>{{ $no }} - {{ $name }}</option>@endforeach</select>
                @if($me->scoped())<input type="hidden" name="assembly_no" value="{{ $me->workAssembly() }}">@endif</div>
            <div class="col-md-4"><label class="form-label">{{ t('col.boothNo') }}</label><select name="booth_no" class="form-select" data-pick-booth><option value="">-</option>
                @foreach($booths as $b)<option value="{{ $b }}" @selected($b == old('booth_no', $g->booth_no))>{{ $b }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ t('gr.category') }} *</label><select name="category" class="form-select" required>
                @foreach(\App\Models\Grievance::CATEGORIES as $c)<option value="{{ $c }}" @selected($c === old('category', $g->category))>{{ t('gr.cat.'.$c) }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">{{ t('gr.subject') }} *</label><input name="subject" value="{{ old('subject', $g->subject) }}" class="form-control @error('subject') is-invalid @enderror" required maxlength="200" placeholder="{{ t('gr.subjectHint') }}" data-voice></div>
            <div class="col-12"><label class="form-label">{{ t('gr.description') }}</label><textarea name="description" class="form-control" rows="3" maxlength="2000" data-voice>{{ old('description', $g->description) }}</textarea></div>
            <div class="col-12"><label class="form-label">{{ t('gr.location') }}</label><input name="location" data-pick-address value="{{ old('location', $g->location) }}" class="form-control" maxlength="300"></div>
            <div class="col-md-4"><label class="form-label">{{ t('gr.priority') }}</label><select name="priority" class="form-select">
                @foreach(\App\Models\Grievance::PRIORITIES as $p)<option value="{{ $p }}" @selected($p === old('priority', $g->priority))>{{ t('gr.pr.'.$p) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">{{ t('gr.status') }}</label><select name="status" class="form-select">
                @foreach(\App\Models\Grievance::STATUSES as $s)<option value="{{ $s }}" @selected($s === old('status', $g->status))>{{ t('gr.status.'.$s) }}</option>@endforeach</select></div>
            @if(\Illuminate\Support\Facades\Schema::hasColumn('grievances', 'due_at'))
            <div class="col-md-4"><label class="form-label">{{ t('grv.dueDate') }}</label><input type="date" name="due_date" class="form-control" value="{{ old('due_date', $g->due_at?->format('Y-m-d')) }}">
                <div class="form-text">{{ t('grv.dueHint') }}</div></div>
            @endif
            <div class="col-md-4"><label class="form-label">{{ t('gr.assigned') }}</label><select name="worker_id" class="form-select"><option value="">-</option>
                @foreach($workers as $w)<option value="{{ $w->id }}" @selected($w->id == old('worker_id', $g->worker_id))>{{ $w->label() }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">{{ t('gr.resolution') }}</label><textarea name="resolution" class="form-control" rows="2" maxlength="1000" data-voice>{{ old('resolution', $g->resolution) }}</textarea></div>
        </div>
        <div class="mt-3 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-check2"></i> {{ t('common.save') }}</button>
            <a href="{{ url($g->exists ? '/grievances/'.$g->id : '/grievances') }}" class="btn btn-outline-secondary">{{ t('common.cancel') }}</a></div>
    </form>
</div>
@push('scripts')<script src="{{ versioned_asset('js/worker-pick.js') }}"></script>@endpush
@endsection
