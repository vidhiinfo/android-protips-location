<div class="col-lg-6"><div class="card h-100 chart-card"><div class="card-body">
    <div class="d-flex align-items-start gap-2"><div class="me-auto">
        <h6 class="mb-0">{{ t('dash.chart.'.$key) }}</h6><p class="small text-muted mb-2">{{ t('dash.chart.'.$key.'.desc') }}</p></div>
        <button type="button" class="btn btn-sm btn-outline-secondary no-print" data-toggle-table="{{ $id }}">{{ t('dash.showTable') }}</button></div>
    <div class="chart-box"><canvas id="{{ $id }}" role="img" aria-label="{{ t('dash.chart.'.$key) }}"></canvas></div>
    <div class="chart-table d-none" id="{{ $id }}-table"></div>
</div></div></div>
