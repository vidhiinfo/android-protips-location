<form method="get" class="card card-body py-2 mb-3 no-print"><div class="row g-2 align-items-end">
    @unless($me->scoped())
    <div class="col-7 col-sm-5"><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label><select name="assemblyNo" class="form-select" data-autosubmit>
        @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
    @endunless
    <div class="col-5 col-sm-3"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label><select name="boothNo" class="form-select" data-autosubmit><option value="">{{ t('fs.pickBooth') }}</option>
        @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
    <div class="col-12 col-sm-4 d-flex gap-2">
        <a class="btn btn-sm {{ request()->is('pollday') ? 'btn-primary' : 'btn-outline-primary' }} flex-fill" href="{{ url('/pollday') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}"><i class="bi bi-keyboard"></i> {{ t('poll.byNumber') }}</a>
        <a class="btn btn-sm {{ request()->is('pollday/grid') ? 'btn-primary' : 'btn-outline-primary' }} flex-fill" href="{{ url('/pollday/grid') }}?{{ http_build_query(['assemblyNo' => $assemblyNo, 'boothNo' => $boothNo]) }}"><i class="bi bi-grid-3x3-gap"></i> {{ t('poll.grid') }}</a>
    </div>
</div></form>
