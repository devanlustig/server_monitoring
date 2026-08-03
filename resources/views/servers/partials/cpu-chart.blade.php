<!-- History CPU Chart -->
@php
    $cpuHistory = $server->cpuMetrics()->latest('collected_at')->limit(20)->get()->reverse();
    $chartLabels = $cpuHistory->map(fn($m) => $m->collected_at->format('H:i:s'))->values();
    $chartData = $cpuHistory->map(fn($m) => $m->usage_percent)->values();
@endphp

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white pt-4 pb-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-primary me-2"></i>CPU Usage History</h5>
        <span class="badge bg-light text-muted border">Recent 20 Samples</span>
    </div>
    <div class="card-body">
        <div style="height: 260px; position: relative;">
            <canvas id="cpuChart"></canvas>
        </div>
    </div>
</div>
