@if($report->capped)<div class="alert alert-warning py-2 small">{{ t('report.capped', number_format(\App\Services\Reports\Report::MAX_ROWS)) }}</div>@endif
@if($report->truncated() && empty($autoprint))<div class="alert alert-warning py-2 small">{{ t('report.truncated', \App\Services\Reports\Report::DISPLAY_LIMIT, count($report->rows)) }}</div>@endif
<div class="table-responsive bg-white border rounded"><table class="table table-sm table-striped table-hover mb-0">
    <thead class="table-light"><tr>@foreach($report->headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse(!empty($autoprint) ? $report->rows : $report->displayRows() as $ri => $row)
        <tr>@foreach($row as $ci => $cell)@php($href = empty($autoprint) ? $report->link($ri, $ci) : null)<td class="{{ \App\Services\Reports\Report::isNumber($cell) ? 'text-end' : '' }}" dir="auto">@if($href && (!is_numeric($cell) || $cell > 0))<a href="{{ $href }}" title="{{ t('nav.voterList') }}">{{ \App\Services\Reports\Report::fmt($cell) }}</a>@else{{ \App\Services\Reports\Report::fmt($cell) }}@endif</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report->headers) }}" class="text-center text-muted py-4">{{ t('common.noRecords') }}</td></tr>
    @endforelse
    </tbody>
    @if($report->totals)<tfoot class="table-secondary fw-semibold"><tr>@foreach($report->totals as $ci => $cell)@php($href = empty($autoprint) ? $report->link('totals', $ci) : null)<td class="{{ \App\Services\Reports\Report::isNumber($cell) ? 'text-end' : '' }}">@if($href && $cell > 0)<a href="{{ $href }}">{{ \App\Services\Reports\Report::fmt($cell) }}</a>@else{{ \App\Services\Reports\Report::fmt($cell) }}@endif</td>@endforeach</tr></tfoot>@endif
</table></div>
