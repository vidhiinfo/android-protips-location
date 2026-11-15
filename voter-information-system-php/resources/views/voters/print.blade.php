@extends('layouts.app')
@section('title', t('common.print'))
@section('bare', '1')
@section('bodyClass', 'print-page')
@section('bodyAttrs', $autoprint ? 'data-autoprint=true' : '')
@section('content')
<div class="no-print mb-3 d-flex gap-2">
    <button class="btn btn-primary btn-sm" data-print><i class="bi bi-printer"></i> {{ t('common.print') }}</button>
    <a class="btn btn-outline-secondary btn-sm" href="{{ url('/voters') }}?{{ $f->queryWithSort() }}">{{ t('common.back') }}</a>
</div>
<h5 class="text-center mb-0">{{ $title }}</h5>
<p class="text-center small text-muted">{{ t('pdf.records') }}: {{ count($voters) }} @if($summary) | {{ t('pdf.filters') }}: {{ $summary }} @endif | {{ t('pdf.generated') }} {{ now()->format('d-m-Y H:i') }}</p>
<table>
    <thead><tr><th>#</th><th>{{ t('list.col.ac') }}</th><th>{{ t('list.col.booth') }}</th><th>{{ t('list.col.sr') }}</th><th>{{ t('col.epicNo') }}</th><th>{{ t('col.name') }}</th>
        <th>{{ t('list.col.relative') }}</th><th>{{ t('col.gender') }}</th><th>{{ t('col.age') }}</th><th>{{ t('col.houseNo') }}</th><th>{{ t('col.address') }}</th><th>{{ t('col.mobile') }}</th></tr></thead>
    <tbody>@foreach($voters as $i => $v)
        <tr><td>{{ $i + 1 }}</td><td>{{ $v->assembly_no }}</td><td>{{ $v->booth_no }}</td><td>{{ $v->serial_no }}</td><td>{{ $v->epic_no }}</td>
            <td>{{ $v->name }}@if($v->name_local)<span class="local-text" dir="auto">{{ $v->name_local }}</span>@endif</td>
            <td>{{ $v->relationDisplay() }}@if($v->relative_name_local)<span class="local-text" dir="auto">{{ $v->relative_name_local }}</span>@endif</td>
            <td>{{ $v->genderLabel() }}</td><td>{{ $v->age }}</td><td>{{ $v->house_no }}</td>
            <td>{{ $v->fullAddress() }}@if($v->address_local)<span class="local-text" dir="auto">{{ $v->address_local }}</span>@endif</td><td>{{ $v->mobile }}</td></tr>
    @endforeach</tbody>
</table>
@endsection
