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

            <!-- Daily Storage Growth Section -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Daily Storage Growth</h6>
                    <small class="text-muted">Last 5 Days (Click growth value for details)</small>
                </div>
                @if(!empty($dailyGrowth))
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-secondary fw-semibold">Date</th>
                                    <th class="text-end text-secondary fw-semibold">Growth</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dailyGrowth as $item)
                                    <tr>
                                        <td class="fw-medium text-dark">{{ $item->dateFormatted }}</td>
                                        <td class="text-end fw-bold">
                                            <button type="button" 
                                                    class="btn btn-sm btn-link text-decoration-none p-0 fw-bold btn-disk-growth-detail"
                                                    data-server-id="{{ $server->id }}"
                                                    data-date="{{ $item->date }}"
                                                    data-date-formatted="{{ $item->dateFormatted }}"
                                                    data-growth-formatted="{{ $item->growthFormatted }}">
                                                @if($item->growthBytes > 0)
                                                    <span class="text-danger"><i class="bi bi-arrow-up-right me-1"></i>{{ $item->growthFormatted }} <i class="bi bi-search ms-1 small"></i></span>
                                                @elseif($item->growthBytes < 0)
                                                    <span class="text-success"><i class="bi bi-arrow-down-right me-1"></i>{{ $item->growthFormatted }} <i class="bi bi-search ms-1 small"></i></span>
                                                @else
                                                    <span class="text-muted">{{ $item->growthFormatted }} <i class="bi bi-search ms-1 small"></i></span>
                                                @endif
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted small text-center py-2">
                        No daily storage growth data available.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>


