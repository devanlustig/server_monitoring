<!-- Memory Breakdown -->
<div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white pt-4 pb-3 border-bottom">
            <h5 class="mb-0 fw-bold"><i class="bi bi-memory text-info me-2"></i>Memory Metrics</h5>
        </div>
        <div class="card-body">
            @if($latestMemory)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark">RAM Usage</span>
                    <span class="fw-bold text-info fs-5">{{ number_format($latestMemory->usage_percent, 1) }}%</span>
                </div>
                <div class="progress mb-4 bg-light" style="height: 10px; border-radius: 5px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $latestMemory->usage_percent }}%;"></div>
                </div>
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Used</div>
                            <div class="fw-bold text-dark">{{ number_format($latestMemory->used / 1073741824, 2) }} GB</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Free / Avail</div>
                            <div class="fw-bold text-dark">{{ number_format(($latestMemory->available ?? $latestMemory->free) / 1073741824, 2) }} GB</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Total</div>
                            <div class="fw-bold text-dark">{{ number_format($latestMemory->total / 1073741824, 2) }} GB</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-muted py-4 text-center">
                    <i class="bi bi-info-circle fs-3 d-block mb-2 text-secondary opacity-50"></i>
                    No RAM metric sample available yet.
                </div>
            @endif
        </div>
    </div>
</div>
