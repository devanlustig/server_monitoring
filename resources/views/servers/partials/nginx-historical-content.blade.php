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

                <!-- Request & Endpoint Analysis Node Graph Panel -->
                <div id="nginx-request-analysis-container" class="mt-4 pt-3 border-top" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-diagram-3-fill me-2"></i>Request & Endpoint Breakdown
                            </h6>
                            <small class="text-muted">Analyzed Window: <span id="nginx-analysis-timestamp" class="fw-semibold text-dark">-</span></small>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" id="nginx-close-analysis-btn">
                            <i class="bi bi-x-lg me-1"></i>Close Analysis
                        </button>
                    </div>

                    <!-- Summary Badges -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                                <span class="small text-muted">Total Requests in Window</span>
                                <span class="fw-bold fs-6" id="nginx-analysis-total-req">0</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                                <span class="small text-muted">Active Endpoints</span>
                                <span class="fw-bold fs-6 text-primary" id="nginx-analysis-active-ep">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="nginx-analysis-loading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                        <span class="text-muted small">Analyzing requests & resolving endpoint sources...</span>
                    </div>

                    <!-- Empty State -->
                    <div id="nginx-analysis-empty" class="text-center py-4 d-none">
                        <i class="bi bi-inbox fs-3 text-muted d-block mb-1"></i>
                        <span class="text-muted small">No request logs recorded near this timestamp.</span>
                    </div>

                    <!-- Node Graph Visual & Details Panel -->
                    <div id="nginx-analysis-content" class="row g-3 align-items-start">
                        <!-- Node Graph Visual (Left Column) -->
                        <div class="col-lg-7">
                            <div class="border rounded p-3 bg-light position-relative d-flex flex-column align-items-center" style="min-height: 320px;">
                                <!-- Parent Node: Total Requests -->
                                <div class="card text-center shadow-sm border-primary mb-4 node-interactive" id="nginx-node-parent" style="width: 180px; cursor: pointer; transition: transform 0.15s ease;">
                                    <div class="card-header bg-primary text-white py-1 small fw-bold">Total Requests</div>
                                    <div class="card-body py-2">
                                        <h4 class="mb-0 fw-bold text-primary" id="nginx-parent-req-val">0</h4>
                                        <small class="text-muted" id="nginx-parent-req-rate">0 req/min</small>
                                    </div>
                                </div>

                                <!-- Children Endpoint Nodes Container -->
                                <div id="nginx-endpoint-nodes-list" class="d-flex justify-content-center flex-wrap gap-2 w-100">
                                    <!-- Populated dynamically by JS -->
                                </div>
                            </div>
                        </div>

                        <!-- Selected Endpoint Detail Panel (Right Column) -->
                        <div class="col-lg-5">
                            <div class="card border shadow-sm">
                                <div class="card-header bg-white py-2 fw-bold small d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-info-circle me-1 text-primary"></i>Endpoint Details</span>
                                    <span class="badge bg-secondary" id="nginx-ep-detail-badge">Select Endpoint</span>
                                </div>
                                <div class="card-body p-3">
                                    <div id="nginx-ep-detail-body">
                                        <p class="text-muted small mb-0">Click any endpoint node on the graph to inspect HTTP status breakdown and source resolution details.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
