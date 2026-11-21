@extends('layouts.app')
@section('title', t('grv.deadlines'))
@section('content')
<div class="container px-3" style="max-width:1000px">
    @include('partials.flash')
    <div class="d-flex align-items-center gap-2 mb-1">
        <a href="{{ url('/grievances') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
        <h4 class="mb-0"><i class="bi bi-alarm"></i> {{ t('grv.deadlines') }}</h4>
    </div>
    <p class="small text-muted">{{ t('grv.deadlinesIntro') }}</p>
    @unless($alerts)<div class="alert alert-info small py-2">{{ t('grv.alertsOff') }}</div>@endunless
    <form method="post" action="{{ url('/grievances/deadlines') }}">@csrf
        <div class="table-responsive bg-white border rounded mb-3"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>{{ t('gr.category') }}</th><th style="width:8rem">{{ t('grv.days') }}</th><th>{{ t('grv.escalate1') }}</th><th>{{ t('grv.escalate2') }}</th></tr></thead>
            <tbody>
            @foreach(\App\Models\Grievance::CATEGORIES as $c)
            <tr><td>{{ t('gr.cat.' . $c) }}</td>
                <td><input type="number" name="days[{{ $c }}]" min="1" max="365" value="{{ $sla[$c]['days'] ?? 7 }}" class="form-control form-control-sm"></td>
                @foreach(['to' => 'escalate_to', 'to2' => 'escalate_to2'] as $fn => $col)
                <td><select name="{{ $fn }}[{{ $c }}]" class="form-select form-select-sm"><option value="">--</option>
                    @foreach($users as $u)<option value="{{ $u }}" @selected(($sla[$c][$col] ?? null) === $u)>{{ $u }}</option>@endforeach</select></td>
                @endforeach</tr>
            @endforeach
            </tbody>
        </table></div>
        <button class="btn btn-primary">{{ t('common.save') }}</button>
    </form>
</div>
@endsection
