@php
    $cpuUsage = $latestMetric?->usage_percent ?? 0;
    if($cpuUsage < 60) {
        $cpuBadge = "success";
        $cpuStatus = "Healthy";
    } elseif($cpuUsage < 85) {
        $cpuBadge = "warning";
        $cpuStatus = "Warning";
    } else {
        $cpuBadge = "danger";
        $cpuStatus = "Critical";
    }
@endphp

<!-- CPU Details & Load -->
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white pt-4 pb-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>CPU & Load Averages</h5>
            <span class="badge bg-{{ $cpuBadge }} bg-opacity-10 text-{{ $cpuBadge }} border border-{{ $cpuBadge }} border-opacity-25 rounded-pill px-3 py-1">
                {{ $cpuStatus }}
            </span>
        </div>
        <div class="card-body">
            <div class="p-3 bg-light rounded-3 mb-4 text-center">
                <div class="text-muted small fw-bold text-uppercase mb-1">Current Usage</div>
                <div class="display-5 fw-bold text-dark mb-2">
                    {{ $latestMetric?->usage_percent !== null ? number_format($latestMetric->usage_percent, 2).'%' : '--' }}
                </div>
                <div class="progress bg-white shadow-sm" style="height: 12px; border-radius: 6px;">
                    <div class="progress-bar bg-{{ $cpuBadge }}" role="progressbar" style="width: {{ $cpuUsage }}%;"></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle info-table mb-0">
                    <tbody>
                        <tr>
                            <th><i class="bi bi-1-circle me-2 text-muted"></i>Load 1 Minute</th>
                            <td><span class="fw-bold">{{ $latestMetric->load_1 ?? '-' }}</span></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-5-circle me-2 text-muted"></i>Load 5 Minutes</th>
                            <td><span class="fw-bold">{{ $latestMetric->load_5 ?? '-' }}</span></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-clock me-2 text-muted"></i>Load 15 Minutes</th>
                            <td><span class="fw-bold">{{ $latestMetric->load_15 ?? '-' }}</span></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-calendar-event me-2 text-muted"></i>Last Sample Collected</th>
                            <td>{{ $latestMetric?->collected_at?->format('d M Y H:i:s') ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
