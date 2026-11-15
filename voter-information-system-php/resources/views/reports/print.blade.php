@extends('layouts.app')
@section('title', $report->title)
@section('bare', '1')
@section('bodyClass', 'print-page')
@section('bodyAttrs', 'data-autoprint=true')
@section('content')
<div class="no-print mb-3"><button class="btn btn-primary btn-sm" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button></div>
<h5 class="text-center mb-0">{{ $report->title }}</h5>
<p class="text-center small text-muted">{{ $report->subtitle }} | {{ t('pdf.generated') }} {{ now()->format('d-m-Y H:i') }}</p>
@include('reports._table')
@endsection
