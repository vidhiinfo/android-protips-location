@extends('layouts.app')
@section('title', t('greet.title'))
@section('content')
<div class="container px-3" style="max-width:1100px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-gift"></i> {{ t('greet.title') }}</h4>
    <p class="small text-muted">{{ t('greet.intro') }}</p>
    @if($silence)<div class="alert alert-warning py-2 small">{{ t('greet.silence', $silence) }}</div>@endif
    <div class="row g-3">
        <div class="col-lg-5">
            <form method="post" action="{{ url('/messages/greetings') }}" class="card card-body">@csrf
                <h6>{{ t('greet.add') }}</h6>
                <label class="form-label small mb-0">{{ t('greet.occasion') }}</label>
                <select name="kind" class="form-select mb-2">@foreach(array_keys(\App\Services\Messaging\GreetingService::KINDS) as $k)<option value="{{ $k }}">{{ t('greet.kind.' . $k) }}</option>@endforeach</select>
                <label class="form-label small mb-0">{{ t('msg.template') }}</label>
                <select name="template_id" class="form-select mb-2" required><option value="">--</option>
                    @foreach($templates as $tp)<option value="{{ $tp->id }}">{{ t('msg.channel.' . $tp->channel) }} · {{ $tp->name }} ({{ $tp->language }})</option>@endforeach</select>
                <div class="row g-2 mb-2">
                    @unless($me->scoped())
                    <div class="col-7"><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assembly_no" class="form-select"><option value="">{{ t('common.all') }}</option>
                        @foreach($assemblies as $no => $name)<option value="{{ $no }}">{{ $no }} - {{ $name }}</option>@endforeach</select></div>
                    @endunless
                    <div class="col-5"><label class="form-label small mb-0">{{ t('greet.sendAt') }}</label><input type="time" name="send_at" value="09:00" class="form-control" required></div>
                </div>
                <div class="small text-muted mb-2">{{ t('greet.hint') }}</div>
                <button class="btn btn-primary">{{ t('common.save') }}</button>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="table-responsive bg-white border rounded mb-3"><table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>{{ t('greet.occasion') }}</th><th>{{ t('msg.template') }}</th><th>{{ t('col.assemblyNo') }}</th><th>{{ t('greet.sendAt') }}</th><th>{{ t('greet.lastRun') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($rules as $g)
                <tr class="{{ $g->active ? '' : 'text-muted' }}">
                    <td>{{ t('greet.kind.' . $g->kind) }}</td>
                    <td class="small">{{ $templates[$g->template_id]->name ?? '#' . $g->template_id }}</td>
                    <td>{{ $g->assembly_no ?: t('common.all') }}</td><td>{{ $g->send_at }}</td>
                    <td class="small">{{ $g->last_run_on ?: '-' }}@if($g->last_campaign_id) · <a href="{{ url('/messages/campaigns/' . $g->last_campaign_id) }}">{{ t('greet.campaign') }}</a>@endif</td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="{{ url('/messages/greetings/' . $g->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $g->active ? t('greet.pause') : t('greet.resume') }}</button></form>
                        <form method="post" action="{{ url('/messages/greetings/' . $g->id) }}" class="d-inline" data-confirm="{{ t('greet.deleteConfirm') }}">@csrf<input type="hidden" name="delete" value="1"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-3">{{ t('greet.none') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <h6>{{ t('greet.upcoming') }}</h6>
            <div class="table-responsive bg-white border rounded"><table class="table table-sm mb-0 text-center">
                <thead class="table-light"><tr><th></th>@foreach(array_keys($upcoming['BIRTHDAY']) as $d)<th class="small">{{ \Carbon\Carbon::parse($d)->format('d M') }}</th>@endforeach</tr></thead>
                <tbody>@foreach($upcoming as $k => $days)<tr><th class="text-start small">{{ t('greet.kind.' . $k) }}</th>@foreach($days as $n)<td>{{ $n }}</td>@endforeach</tr>@endforeach</tbody>
            </table></div>
            <div class="small text-muted mt-1">{{ t('greet.upcomingHint') }}</div>
        </div>
    </div>
</div>
@endsection
