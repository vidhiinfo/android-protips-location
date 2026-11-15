<select name="electionId" class="form-select form-select-sm" data-autosubmit>
    @if($elections->isEmpty())<option value="">{{ t('result.noElection') }}</option>@endif
    @foreach($elections as $e)<option value="{{ $e->id }}" @selected($election && $e->id == $election->id)>{{ $e->name }}</option>@endforeach
</select>
