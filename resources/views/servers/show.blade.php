@extends('layouts.app')

@section('content')

<!-- Bootstrap Icons & Chart.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .page-header {
        background: linear-gradient(135deg, #0d6efd, #8e1592);
        color: white;
        padding: 2rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.15);
    }
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-radius: 12px;
        border: none;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .info-table th {
        color: #6c757d;
        font-weight: 600;
        font-size: 0.875rem;
        width: 40%;
    }
    .info-table td {
        font-weight: 500;
        font-size: 0.9rem;
    }
    .partial-contents {
        display: contents;
    }

    /* Auto Refresh Indicator Animations */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin-icon {
        display: inline-block;
        animation: spin 0.8s linear infinite;
    }

    @keyframes highlightGreen {
        0% { background-color: rgba(25, 135, 84, 0.4); color: #fff; padding: 2px 6px; border-radius: 4px; }
        100% { background-color: transparent; }
    }
    .highlight-fade {
        animation: highlightGreen 0.8s ease-out;
    }

    @keyframes pulseBadge {
        0% { transform: scale(1); }
        50% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }
    .pulse-once {
        animation: pulseBadge 0.5s ease-in-out;
        display: inline-block;
    }
</style>

<div id="partial-header">
    @include('servers.partials.header')
</div>

<div class="row g-4 mb-4">
    <div id="partial-system-information" class="partial-contents">
        @include('servers.partials.system-information')
    </div>
    <div id="partial-cpu-detail" class="partial-contents">
        @include('servers.partials.cpu-detail')
    </div>
</div>

<div class="row g-4 mb-4">
    <div id="partial-memory-detail" class="partial-contents">
        @include('servers.partials.memory-detail')
    </div>
    <div id="partial-disk-detail" class="partial-contents">
        @include('servers.partials.disk-detail')
    </div>
</div>

<div id="partial-cpu-chart">
    @include('servers.partials.cpu-chart')
</div>

<!-- CPU Root Cause / Correlation Analysis Node Graph -->
<div class="card shadow-sm border-0 mb-4 bg-white" id="cpu-analysis-card" style="display: none;">
    <div class="card-header bg-white pt-4 pb-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-diagram-3-fill text-primary me-2"></i>
            CPU Root Cause & Correlation Analysis
        </h5>
        <span class="badge bg-primary bg-opacity-10 text-primary border" id="analysis-timestamp">Timestamp: N/A</span>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Node Graph Panel -->
            <div class="col-lg-7 border-end pb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Click nodes to inspect data details.</span>
                    <button class="btn btn-outline-primary btn-sm fw-semibold" id="btn-analyze-current">
                        <i class="bi bi-play-circle-fill me-1"></i>Analyze Current
                    </button>
                </div>
                <!-- Node Graph Visual (SVG/Flex Layout) -->
                <div class="position-relative border rounded p-4 bg-light d-flex flex-column align-items-center justify-content-center" style="min-height: 380px;" id="node-graph-container">
                    <!-- Main CPU Node -->
                    <div class="card text-center shadow border-danger mb-4 node-interactive" data-node="cpu" style="width: 170px; cursor: pointer; transition: transform 0.15s ease; z-index: 10;">
                        <div class="card-header bg-danger text-white py-1 small fw-bold">Total CPU Load</div>
                        <div class="card-body py-2">
                            <h4 class="mb-0 fw-bold text-danger" id="node-val-cpu">0%</h4>
                            <small class="text-muted" id="node-lbl-cpu">Spike</small>
                        </div>
                    </div>
                    <!-- Connection Lines Placeholder (Simple CSS Grid/Flex layout for Node Relationships) -->
                    <div class="d-flex justify-content-center flex-wrap gap-3 w-100 mt-2">
                        <!-- Application Request Node -->
                        <div class="card text-center shadow-sm border-secondary node-interactive" data-node="app" style="width: 135px; cursor: pointer; transition: transform 0.15s ease;">
                            <div class="card-header bg-light py-1 small fw-bold text-truncate">App Request</div>
                            <div class="card-body py-2 px-1">
                                <h6 class="mb-1 fw-bold text-dark text-truncate" id="node-val-app">0 req/min</h6>
                                <span class="badge bg-secondary" id="node-badge-app">Score: 0</span>
                            </div>
                        </div>
                        <!-- Database Node -->
                        <div class="card text-center shadow-sm border-secondary node-interactive" data-node="db" style="width: 135px; cursor: pointer; transition: transform 0.15s ease;">
                            <div class="card-header bg-light py-1 small fw-bold text-truncate">PostgreSQL</div>
                            <div class="card-body py-2 px-1">
                                <h6 class="mb-1 fw-bold text-dark text-truncate" id="node-val-db">0 active</h6>
                                <span class="badge bg-secondary" id="node-badge-db">Score: 0</span>
                            </div>
                        </div>
                        <!-- Cron Node -->
                        <div class="card text-center shadow-sm border-secondary node-interactive" data-node="cron" style="width: 135px; cursor: pointer; transition: transform 0.15s ease;">
                            <div class="card-header bg-light py-1 small fw-bold text-truncate">Cron / Task</div>
                            <div class="card-body py-2 px-1">
                                <h6 class="mb-1 fw-bold text-dark text-truncate" id="node-val-cron">0% CPU</h6>
                                <span class="badge bg-secondary" id="node-badge-cron">Score: 0</span>
                            </div>
                        </div>
                        <!-- Queue Node -->
                        <div class="card text-center shadow-sm border-secondary node-interactive" data-node="queue" style="width: 135px; cursor: pointer; transition: transform 0.15s ease;">
                            <div class="card-header bg-light py-1 small fw-bold text-truncate">Queue Workers</div>
                            <div class="card-body py-2 px-1">
                                <h6 class="mb-1 fw-bold text-dark text-truncate" id="node-val-queue">0% CPU</h6>
                                <span class="badge bg-secondary" id="node-badge-queue">Score: 0</span>
                            </div>
                        </div>
                        <!-- System Node -->
                        <div class="card text-center shadow-sm border-secondary node-interactive" data-node="system" style="width: 135px; cursor: pointer; transition: transform 0.15s ease;">
                            <div class="card-header bg-light py-1 small fw-bold text-truncate">System Processes</div>
                            <div class="card-body py-2 px-1">
                                <h6 class="mb-1 fw-bold text-dark text-truncate" id="node-val-system">-</h6>
                                <span class="badge bg-secondary" id="node-badge-system">Score: 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Details Panel -->
            <div class="col-lg-5 ps-lg-4 pt-3 pt-lg-0">
                <div class="mb-3">
                    <h6 class="fw-bold mb-1 text-primary"><i class="bi bi-chat-left-text-fill me-1"></i>Correlation Summary</h6>
                    <div class="alert alert-light border p-3 rounded" id="analysis-summary-box">
                        <div class="mb-2"><strong>Likely Cause:</strong> <span class="badge bg-warning text-dark" id="summary-cause">None</span></div>
                        <div class="mb-2"><strong>Confidence Level:</strong> <span class="fw-bold text-muted" id="summary-confidence">No Evidence</span></div>
                        <hr class="my-2">
                        <div class="small text-muted mb-0">Select a spike on the CPU chart above or click nodes for breakdown.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <h6 class="fw-bold mb-2 text-primary" id="detail-panel-title"><i class="bi bi-info-circle-fill me-1"></i>Analysis Evidence</h6>
                    <ul class="list-group list-group-flush border rounded" id="evidence-list" style="max-height: 200px; overflow-y: auto;">
                        <li class="list-group-item text-muted small py-2">No timestamp analyzed yet.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@include('servers.partials.cpu-chart-script')
@include('servers.partials.disk-growth-detail-modal')