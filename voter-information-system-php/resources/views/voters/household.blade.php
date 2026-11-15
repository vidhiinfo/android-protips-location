@extends('layouts.app')
@section('title', t('household.title'))
@section('bare', '1')
@section('bodyClass', 'print-page')
@section('content')
<div class="no-print mb-3"><button class="btn btn-primary btn-sm" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button></div>
@include('voters.household-content')
@endsection
