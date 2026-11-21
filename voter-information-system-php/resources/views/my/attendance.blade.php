@extends('layouts.app')
@section('title', t('att.my'))
@section('content')
<div class="container px-3" style="max-width:640px">
    @include('partials.flash')
    <h4 class="mb-1"><i class="bi bi-calendar-check"></i> {{ t('att.my') }}</h4>
    <p class="small text-muted">{{ t('att.myIntro') }}</p>
    <form method="post" action="{{ url('/my/attendance') }}" class="card card-body mb-3">@csrf
        <input type="hidden" name="lat"><input type="hidden" name="lng"><input type="hidden" name="acc">
        <div class="mb-2">
            @if(!$today)<span class="badge text-bg-secondary">{{ t('att.notYet') }}</span>
            @elseif(!$today->check_out)<span class="badge text-bg-success">{{ t('att.inSince', substr($today->check_in, 11, 5)) }}</span>
            @else<span class="badge text-bg-primary">{{ t('att.dayDone', substr($today->check_in, 11, 5), substr($today->check_out, 11, 5)) }}</span>@endif
        </div>
        @if(!$today || !$today->check_out)
        <input name="note" class="form-control mb-2" maxlength="300" placeholder="{{ t('att.notePh') }}">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-geo="attGeo" data-l-taking="{{ t('off.locTaking') }}" data-l-failed="{{ t('off.locFailed') }}" data-l-ready="{{ t('off.locSaved') }}"><i class="bi bi-geo-alt"></i> {{ t('off.location') }}</button>
            <span class="small text-muted" id="attGeo">{{ t('att.locOptional') }}</span>
        </div>
        <button class="btn btn-lg {{ $today ? 'btn-warning' : 'btn-success' }} w-100"><i class="bi bi-{{ $today ? 'box-arrow-right' : 'box-arrow-in-right' }}"></i> {{ $today ? t('att.checkOut') : t('att.checkIn') }}</button>
        @endif
    </form>
    <h6>{{ t('att.last30') }}</h6>
    <div class="list-group">
        @forelse($history as $h)
        <div class="list-group-item d-flex gap-2"><span class="fw-semibold me-auto">{{ $h->day }}</span>
            <span>{{ substr($h->check_in, 11, 5) }} - {{ $h->check_out ? substr($h->check_out, 11, 5) : '…' }}</span></div>
        @empty
        <div class="list-group-item text-muted">{{ t('list.empty') }}</div>
        @endforelse
    </div>
</div>
@endsection
