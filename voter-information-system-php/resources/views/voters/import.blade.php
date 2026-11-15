@extends('layouts.app')
@section('title', t('nav.import'))
@section('content')
<div class="container" style="max-width:850px">
    <h4 class="mb-3">{{ t('import.title') }}</h4>
    <div class="card mb-3"><div class="card-body">
        <ol class="mb-3"><li><a href="{{ url('/voters/import/template') }}">{{ t('import.step1') }}</a></li><li>{{ t('import.step2') }}</li><li>{{ t('import.step3') }}</li><li>{{ t('import.step4') }}</li></ol>
        @if(!empty($error))<div class="alert alert-danger py-2">{{ $error }}</div>@endif
        <form action="{{ url('/voters/import') }}" method="post" enctype="multipart/form-data" class="d-flex gap-2">@csrf
            <input type="file" name="file" accept=".xlsx" class="form-control" required>
            <button class="btn btn-primary text-nowrap"><i class="bi bi-upload"></i> {{ t('import.upload') }}</button></form>
    </div></div>
    @if(!empty($result))
    <div class="card"><div class="card-body">
        <h6>{{ t('import.result') }}</h6>
        <p class="mb-2"><span class="badge text-bg-success">{{ t('import.added', $result['inserted']) }}</span>
            <span class="badge text-bg-primary">{{ t('import.updated', $result['updated']) }}</span>
            <span class="badge text-bg-danger">{{ t('import.errors', count($result['errors'])) }}</span></p>
        @if($result['errors'])<ul class="small text-danger mb-0">@foreach($result['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <a href="{{ url('/voters') }}" class="btn btn-sm btn-outline-primary mt-2">{{ t('import.goToList') }}</a>
    </div></div>
    @endif
</div>
@endsection
