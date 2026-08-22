<!-- Historical Analytics -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Historical Analytics</h5>
                <div class="d-flex gap-2">
                    <select id="historyMetric" class="form-select form-select-sm" style="width: auto;">
                        @foreach(\App\Services\Monitoring\Support\MetricNames::nginxSelectable() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select id="historyPeriod" class="form-select form-select-sm" style="width: auto;">
                        <option value="1h">Last 1 Hour</option>
                        <option value="6h">Last 6 Hours</option>
                        <option value="24h" selected>Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                    </select>
                </div>
            </div>

            <div class="card-body">
                <div class="row mb-4 text-center g-3">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Current</div>
                            <h4 id="summaryCurrent" class="fw-bold mb-0 mt-1">
                                {{ number_format($history['summary']->current, 1) }}
                            </h4>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Average</div>
                            <h4 id="summaryAverage" class="fw-bold mb-0 mt-1">
                                {{ number_format($history['summary']->average, 1) }}
                            </h4>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Peak</div>
                            <h4 id="summaryMaximum" class="fw-bold mb-0 mt-1">
                                {{ number_format($history['summary']->maximum, 1) }}
                            </h4>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Minimum</div>
                            <h4 id="summaryMinimum" class="fw-bold mb-0 mt-1">
                                {{ number_format($history['summary']->minimum, 1) }}
                            </h4>
                        </div>
                    </div>
                </div>

                <div style="height: 320px; position: relative;">
                    <div id="historyChartEmptyState" class="d-none position-absolute top-50 start-50 translate-middle text-muted text-center">
                        <i class="bi bi-graph-up fs-2 d-block mb-1"></i>
                        <span class="small">No historical data available for this metric/period.</span>
                    </div>
                    <canvas id="historyResponseChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
