@extends('layouts.app')
@section('title', t('admin.system'))
@section('content')
<div class="container" style="max-width:900px">
    <h4 class="mb-3"><i class="bi bi-hdd-stack"></i> {{ t('admin.system') }}</h4>
    <div class="card"><table class="table mb-0">
        @foreach($info as $k => $v)<tr><th style="width:35%">{{ $k === 'admin.sys.java' ? 'PHP / Laravel' : t($k) }}</th><td class="text-break">{{ $v }}</td></tr>@endforeach
        <tr><th>{{ t('admin.sys.languages') }}</th><td>@foreach($languages as $l)<span class="badge text-bg-light border me-1">{{ $l[0] }}</span>@endforeach</td></tr>
    </table></div>
    <p class="small text-muted mt-2">{{ t('admin.sys.charsetHint') }}</p>
</div>
@endsection
