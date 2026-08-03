<!-- Disk Breakdown -->
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white pt-4 pb-3 border-bottom">
            <h5 class="mb-0 fw-bold"><i class="bi bi-hdd-rack text-primary me-2"></i>Disk Metrics</h5>
        </div>
        <div class="card-body">
            @if($latestDisk)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark">Disk Usage</span>
                    <span class="fw-bold text-primary fs-5">{{ number_format($latestDisk->usage_percent, 1) }}%</span>
                </div>
                <div class="progress mb-4 bg-light" style="height: 10px; border-radius: 5px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $latestDisk->usage_percent }}%;"></div>
                </div>
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Used</div>
                            <div class="fw-bold text-dark">{{ number_format($latestDisk->used / 1073741824, 2) }} GB</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Available</div>
                            <div class="fw-bold text-dark">{{ number_format($latestDisk->available / 1073741824, 2) }} GB</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Total</div>
                            <div class="fw-bold text-dark">{{ number_format($latestDisk->total / 1073741824, 2) }} GB</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-muted py-4 text-center">
                    <i class="bi bi-info-circle fs-3 d-block mb-2 text-secondary opacity-50"></i>
                    No Disk metric sample available yet.
                </div>
            @endif
        </div>
    </div>
</div>
