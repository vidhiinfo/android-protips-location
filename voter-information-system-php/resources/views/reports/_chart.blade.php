@if($report->note)<p class="small text-muted mb-2"><i class="bi bi-info-circle"></i> {{ $report->note }}</p>@endif
@if($report->chart)
<div class="card mb-3 no-print-break"><div class="card-body"><div class="chart-box" style="height:{{ !empty($report->chart['horizontal']) ? max(260, 26 * count($report->chart['labels'])) : 300 }}px">
    <canvas data-chart="{{ json_encode($report->chart) }}" data-empty="{{ t('common.noRecords') }}" role="img" aria-label="{{ $report->title }}"></canvas></div></div></div>
@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script src="{{ versioned_asset('js/analytics.js') }}"></script>
@endpush
@endif
