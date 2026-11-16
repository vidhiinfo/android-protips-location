@extends('layouts.app')
@section('title', t('poll.title'))
@section('content')
<div class="container-fluid px-3" style="max-width:1100px">
    <h4 class="mb-2"><i class="bi bi-grid-3x3-gap"></i> {{ t('poll.title') }}</h4>
    @include('partials.flash')
    @if(!$e)<div class="alert alert-warning">{{ t('poll.noElection') }}</div>@else
    @include('pollday._pick')
    @if($boothNo)
    <div class="d-flex flex-wrap gap-3 align-items-center small mb-2">
        <span><b data-poll-voted>{{ $rows->where('voted', 1)->count() }}</b> / {{ $rows->count() }} {{ t('poll.votedLower') }}</span>
        <span><span class="poll-cell voted d-inline-block">12</span> {{ t('poll.voted.yes') }}</span>
        <span><span class="poll-cell sup d-inline-block">12</span> {{ t('an.support') }}</span>
        <span class="text-muted">{{ t('poll.gridHint') }}</span>
    </div>
    <div class="poll-grid" data-toggle-url="{{ url('/pollday/toggle') }}" data-token="{{ csrf_token() }}">
        @foreach($rows as $v)<button type="button" class="poll-cell {{ $v->voted ? 'voted' : '' }} {{ $v->st === 'S' ? 'sup' : '' }}" data-id="{{ $v->id }}" title="{{ $v->name }}">{{ $v->serial_no }}</button>@endforeach
    </div>
    @else <div class="alert alert-info">{{ t('poll.pickBooth') }}</div> @endif
    @endif
</div>
@endsection
