{{-- Set caste / sub caste for every voter of this house in one go. params: voter, household, casteTree --}}
@if(can('VOTER_EDIT') && count($household) > 0)
<form method="post" action="{{ url('/voters/'.$voter->id.'/household/caste') }}" class="border rounded p-2 mt-2 bg-light no-print" target="_top">@csrf
    <div class="small fw-semibold mb-1"><i class="bi bi-people"></i> {{ t('household.casteTitle') }}</div>
    <div class="row g-2 align-items-end">
        <div class="col-sm-4"><label class="form-label small mb-0">{{ t('col.caste') }}</label>
            @if($casteTree)
            <select name="caste" class="form-select form-select-sm" required data-caste-select data-sub-select="hh-sub-{{ $voter->id }}" data-map='@json((object) $casteTree)' data-labels='@json((object) \App\Models\Caste::labels())'>
                <option value="">--</option>
                @foreach(array_keys($casteTree) as $c)<option value="{{ $c }}" @selected($c === $voter->caste)>{{ \App\Models\Caste::labels()[$c] ?? $c }}</option>@endforeach
            </select>
            @else
            <input name="caste" class="form-control form-control-sm" required value="{{ $voter->caste }}" dir="auto">
            @endif</div>
        <div class="col-sm-4"><label class="form-label small mb-0">{{ t('col.subCaste') }}</label>
            @if($casteTree)
            <select name="sub_caste" id="hh-sub-{{ $voter->id }}" class="form-select form-select-sm"><option value="">--</option>
                @foreach($casteTree[$voter->caste] ?? [] as $sc)<option value="{{ $sc }}" @selected($sc === $voter->sub_caste)>{{ \App\Models\Caste::labels()[$sc] ?? $sc }}</option>@endforeach
            </select>
            @else
            <input name="sub_caste" class="form-control form-control-sm" value="{{ $voter->sub_caste }}" dir="auto">
            @endif</div>
        <div class="col-sm-4"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-check2-all"></i> {{ t('household.applyCaste', count($household)) }}</button></div>
    </div>
</form>
@endif
