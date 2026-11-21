@extends('layouts.app')
@section('title', t('task.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1300px">
    @include('partials.flash')
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div class="me-auto"><h4 class="mb-0"><i class="bi bi-list-check"></i> {{ t('task.title') }}</h4><div class="small text-muted">{{ t('task.intro') }}</div></div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            @unless($me->scoped())
            <div><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select form-select-sm" data-autosubmit>
                @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
            @else<input type="hidden" name="assemblyNo" value="{{ $assemblyNo }}">@endunless
            <div><label class="form-label small mb-0">{{ t('task.worker') }}</label><select name="workerId" class="form-select form-select-sm" data-autosubmit><option value="">{{ t('common.all') }}</option>
                @foreach($workers as $w)<option value="{{ $w->id }}" @selected($w->id == $workerId)>{{ $w->label() }}</option>@endforeach</select></div>
            <div><label class="form-label small mb-0">{{ t('task.status') }}</label><select name="status" class="form-select form-select-sm" data-autosubmit>
                @foreach(['OPEN', 'DONE', 'ALL'] as $s)<option value="{{ $s }}" @selected($s === $status)>{{ t('task.status.' . $s) }}</option>@endforeach</select></div>
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/workers/attendance') }}?assemblyNo={{ $assemblyNo }}"><i class="bi bi-calendar-check"></i> {{ t('att.title') }}</a>
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <form method="post" action="{{ url('/workers/tasks' . ($edit ? '/' . $edit->id : '')) }}" class="card card-body">@csrf
                <input type="hidden" name="assembly_no" value="{{ $assemblyNo }}">
                <h6>{{ $edit ? t('task.edit') : t('task.add') }}</h6>
                <label class="form-label small mb-0">{{ t('task.what') }}</label>
                <input name="title" class="form-control mb-2" maxlength="200" required value="{{ old('title', $edit->title ?? '') }}" placeholder="{{ t('task.whatHint') }}">
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small mb-0">{{ t('task.kind') }}</label><select name="kind" class="form-select">
                        @foreach(\App\Services\Workers\TaskService::KINDS as $k)<option value="{{ $k }}" @selected(old('kind', $edit->kind ?? 'SURVEY') === $k)>{{ t('task.kind.' . $k) }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-0">{{ t('task.target') }}</label>
                        <input name="target" type="number" min="1" class="form-control" value="{{ old('target', $edit->target ?? '') }}" placeholder="{{ t('task.targetHint') }}"></div>
                </div>
                <label class="form-label small mb-0">{{ t('task.worker') }}</label>
                <select name="worker_id" class="form-select mb-2"><option value="">--</option>
                    @foreach($workers as $w)<option value="{{ $w->id }}" @selected(old('worker_id', $edit->worker_id ?? '') == $w->id)>{{ $w->label() }}{{ $w->username ? '' : ' - ' . t('task.noLogin') }}</option>@endforeach</select>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="booth_no" class="form-select"><option value="">{{ t('task.workersBooth') }}</option>
                        @foreach($booths as $b)<option value="{{ $b }}" @selected(old('booth_no', $edit->booth_no ?? '') == $b)>{{ $b }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label small mb-0">{{ t('task.due') }}</label>
                        <input name="due_date" type="date" class="form-control" value="{{ old('due_date', $edit->due_date ?? '') }}"></div>
                </div>
                <label class="form-label small mb-0">{{ t('task.notes') }}</label>
                <textarea name="notes" class="form-control mb-2" rows="2" maxlength="1000">{{ old('notes', $edit->notes ?? '') }}</textarea>
                <div class="small text-muted mb-2">{{ t('task.surveyHint') }}@if($reminders) {{ t('task.remindOn') }}@endif</div>
                <div class="d-flex gap-2"><button class="btn btn-primary flex-fill">{{ t('common.save') }}</button>
                    @if($edit)<a href="{{ url('/workers/tasks') }}?assemblyNo={{ $assemblyNo }}" class="btn btn-outline-secondary">{{ t('common.cancel') }}</a>@endif</div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="table-responsive bg-white border rounded"><table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>{{ t('task.what') }}</th><th>{{ t('task.worker') }}</th><th>{{ t('col.boothNo') }}</th><th>{{ t('task.due') }}</th><th style="min-width:150px">{{ t('task.progress') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($tasks as $tk)
                @php($p = $progress[$tk->id])
                @php($late = $tk->status === 'OPEN' && $tk->due_date && $tk->due_date < $today)
                <tr class="{{ $tk->status === 'DONE' ? 'text-muted' : '' }}">
                    <td><div class="fw-semibold">{{ $tk->title }}</div><div class="small text-muted">{{ t('task.kind.' . $tk->kind) }}@if($tk->notes) · {{ \Illuminate\Support\Str::limit($tk->notes, 80) }}@endif</div></td>
                    <td class="small">{{ $tk->worker_id ? ($workerNames[$tk->worker_id] ?? '#' . $tk->worker_id) : ($tk->username ?: '-') }}</td>
                    <td>{{ $tk->booth_no ?: '-' }}</td>
                    <td class="small text-nowrap">@if($tk->due_date)<span class="{{ $late ? 'text-danger fw-semibold' : '' }}">{{ $tk->due_date }}</span>@if($late)<br><span class="badge text-bg-danger">{{ t('task.overdue') }}</span>@endif @else - @endif</td>
                    <td>@if($tk->status === 'DONE')<span class="badge text-bg-success"><i class="bi bi-check-lg"></i> {{ t('task.status.DONE') }}</span>@endif
                        <div class="small">{{ $p['done'] }}{{ $tk->target ? ' / ' . $tk->target : '' }}</div>
                        @if($p['pct'] !== null)<div class="progress" style="height:6px"><div class="progress-bar {{ $p['pct'] >= 100 ? 'bg-success' : '' }}" style="width:{{ $p['pct'] }}%"></div></div>@endif</td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="{{ url('/workers/tasks/' . $tk->id . '/status') }}" class="d-inline">@csrf<input type="hidden" name="status" value="{{ $tk->status === 'DONE' ? 'OPEN' : 'DONE' }}">
                            <button class="btn btn-sm btn-outline-{{ $tk->status === 'DONE' ? 'secondary' : 'success' }}" title="{{ $tk->status === 'DONE' ? t('task.reopen') : t('task.markDone') }}"><i class="bi bi-{{ $tk->status === 'DONE' ? 'arrow-counterclockwise' : 'check-lg' }}"></i></button></form>
                        <a class="btn btn-sm btn-outline-primary" href="{{ url('/workers/tasks') }}?assemblyNo={{ $assemblyNo }}&edit={{ $tk->id }}"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="{{ url('/workers/tasks/' . $tk->id . '/delete') }}" class="d-inline" data-confirm="{{ t('task.deleteConfirm') }}">@csrf<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">{{ t('list.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
