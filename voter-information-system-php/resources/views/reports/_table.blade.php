@if($report->truncated() && empty($autoprint))<div class="alert alert-warning py-2 small">{{ t('report.truncated', \App\Services\Reports\Report::DISPLAY_LIMIT, count($report->rows)) }}</div>@endif
<div class="table-responsive bg-white border rounded"><table class="table table-sm table-striped table-hover mb-0">
    <thead class="table-light"><tr>@foreach($report->headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse(!empty($autoprint) ? $report->rows : $report->displayRows() as $row)
        <tr>@foreach($row as $cell)<td class="{{ \App\Services\Reports\Report::isNumber($cell) ? 'text-end' : '' }}" dir="auto">{{ \App\Services\Reports\Report::fmt($cell) }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report->headers) }}" class="text-center text-muted py-4">{{ t('common.noRecords') }}</td></tr>
    @endforelse
    </tbody>
    @if($report->totals)<tfoot class="table-secondary fw-semibold"><tr>@foreach($report->totals as $cell)<td class="{{ \App\Services\Reports\Report::isNumber($cell) ? 'text-end' : '' }}">{{ \App\Services\Reports\Report::fmt($cell) }}</td>@endforeach</tr></tfoot>@endif
</table></div>
