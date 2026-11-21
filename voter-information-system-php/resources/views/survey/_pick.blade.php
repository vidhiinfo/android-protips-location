{{-- assembly / ward / booth picker (map and offline pages) --}}
<form method="get" class="card card-body py-2 mb-3"><div class="row g-2 align-items-end">
    @unless($me->scoped())
    <div class="col-12 col-sm-5"><label class="form-label small mb-0">{{ t('col.assemblyNo') }}</label>
        <select name="assemblyNo" class="form-select" data-autosubmit>
            @foreach($assemblies as $no => $name)<option value="{{ $no }}" @selected($no == $assemblyNo)>{{ $no }} - {{ $name }}</option>@endforeach</select></div>
    @endunless
    @if($wards)
    <div class="col-6 col-sm-3"><label class="form-label small mb-0">{{ t('col.wardNo') }}</label>
        <select name="wardNo" class="form-select" data-autosubmit><option value="">{{ t('common.all') }}</option>
            @foreach($wards as $w)<option value="{{ $w }}" @selected($w == $wardNo)>{{ $w }}</option>@endforeach</select></div>
    @endif
    <div class="col-6 col-sm-4"><label class="form-label small mb-0">{{ t('col.boothNo') }}</label>
        <select name="boothNo" class="form-select" data-autosubmit><option value="">{{ t('fs.pickBooth') }}</option>
            @foreach($booths as $b)<option value="{{ $b }}" @selected($b == $boothNo)>{{ $b }}</option>@endforeach</select></div>
</div></form>
