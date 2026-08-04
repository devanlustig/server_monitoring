<!-- Page Header -->
<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="mb-0 fw-bold text-white">{{ $server->name }}</h2>
            @if($server->is_online)
                <span id="status-badge" class="badge bg-success bg-opacity-25 text-white border border-light border-opacity-25 rounded-pill px-3 py-1">
                    <i class="bi bi-circle-fill text-success small me-1"></i> Online
                </span>
            @else
                <span id="status-badge" class="badge bg-danger bg-opacity-25 text-white border border-light border-opacity-25 rounded-pill px-3 py-1">
                    <i class="bi bi-circle-fill text-danger small me-1"></i> Offline
                </span>
            @endif
        </div>
        <div class="text-white-50 fs-6 d-flex align-items-center gap-3 flex-wrap">
            <span><i class="bi bi-hdd-network me-1"></i> <code>{{ $server->hostname }}:{{ $server->ssh_port }}</code></span>
            <span><i class="bi bi-clock-history me-1"></i> Checked: <span id="last-checked-time">{{ $server->last_checked_at?->format('Y-m-d H:i:s') ?? '-' }}</span></span>
            <span class="d-inline-flex align-items-center gap-1 bg-white bg-opacity-10 px-2 py-1 rounded small text-white" id="live-indicator">
                <i class="bi bi-arrow-repeat" id="refresh-icon"></i>
                <span id="live-text"><span class="text-success-light fw-bold" style="color: #52c41a;">LIVE</span> • <span id="time-ago-text">Updated just now</span></span>
            </span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('servers.apache', $server) }}" class="btn btn-primary shadow-sm fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-server"></i> Apache
        </a>
        <a href="{{ route('servers.postgresql', $server) }}" class="btn btn-success shadow-sm fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-database-fill-gear"></i> PostgreSQL
        </a>
        <a href="{{ route('servers.edit', $server) }}" class="btn btn-light shadow-sm fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-pencil-square"></i> Edit Server
        </a>
        <a href="{{ route('servers.index') }}" class="btn btn-outline-light fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

@if($server->last_error)
<div class="alert alert-warning alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
        <div>
            <strong>Last SSH Connection Warning / Error:</strong>
            <div class="small mt-1">{{ $server->last_error }}</div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Metric Cards Overview -->
<div class="row g-4 mb-4">
    <!-- Server Status Card -->
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted fw-bold text-uppercase small">Status</span>
                    <div class="stat-icon bg-{{ $server->is_online ? 'success' : 'danger' }} bg-opacity-10 text-{{ $server->is_online ? 'success' : 'danger' }}">
                        <i class="bi bi-power"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-1 {{ $server->is_online ? 'text-success' : 'text-danger' }}">
                    {{ $server->is_online ? 'ONLINE' : 'OFFLINE' }}
                </h4>
                <div class="text-muted small">
                    Last SSH: {{ $server->last_successful_connection_at?->format('d M Y H:i:s') ?? '-' }}
                </div>
            </div>
        </div>
    </div>

    <!-- CPU Card -->
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
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fw-bold text-uppercase small">CPU Usage</span>
                    <div class="stat-icon bg-{{ $cpuBadge }} bg-opacity-10 text-{{ $cpuBadge }}">
                        <i class="bi bi-cpu-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h3 class="fw-bold mb-0 text-dark">
                        {{ $latestMetric?->usage_percent !== null ? number_format($latestMetric->usage_percent, 1).'%' : '--' }}
                    </h3>
                    <span class="badge bg-{{ $cpuBadge }} bg-opacity-10 text-{{ $cpuBadge }} border border-{{ $cpuBadge }} border-opacity-25 rounded-pill px-2 py-1 small">
                        {{ $cpuStatus }}
                    </span>
                </div>
                <div class="progress bg-light" style="height: 6px;">
                    <div class="progress-bar bg-{{ $cpuBadge }}" role="progressbar" style="width: {{ $cpuUsage }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RAM Card -->
    @php
        $ramUsage = $latestMemory?->usage_percent ?? 0;
        $ramBadge = $ramUsage < 60 ? 'info' : ($ramUsage < 85 ? 'warning' : 'danger');
    @endphp
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fw-bold text-uppercase small">RAM Usage</span>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-memory"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h3 class="fw-bold mb-0 text-dark">
                        {{ $latestMemory?->usage_percent !== null ? number_format($latestMemory->usage_percent, 1).'%' : ($server->total_ram_bytes ? number_format($server->total_ram_bytes / 1073741824, 1).' GB' : '--') }}
                    </h3>
                    @if($latestMemory?->used && $latestMemory?->total)
                        <span class="text-muted small">({{ number_format($latestMemory->used / 1073741824, 1) }}/{{ number_format($latestMemory->total / 1073741824, 1) }} GB)</span>
                    @endif
                </div>
                <div class="progress bg-light" style="height: 6px;">
                    <div class="progress-bar bg-{{ $ramBadge }}" role="progressbar" style="width: {{ $ramUsage }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disk Card -->
    @php
        $diskUsage = $latestDisk?->usage_percent ?? 0;
        $diskBadge = $diskUsage < 60 ? 'primary' : ($diskUsage < 85 ? 'warning' : 'danger');
    @endphp
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fw-bold text-uppercase small">Disk Usage</span>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-hdd-rack-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h3 class="fw-bold mb-0 text-dark">
                        {{ $latestDisk?->usage_percent !== null ? number_format($latestDisk->usage_percent, 1).'%' : ($server->total_disk_bytes ? number_format($server->total_disk_bytes / 1073741824, 1).' GB' : '--') }}
                    </h3>
                    @if($latestDisk?->used && $latestDisk?->total)
                        <span class="text-muted small">({{ number_format($latestDisk->used / 1073741824, 1) }}/{{ number_format($latestDisk->total / 1073741824, 1) }} GB)</span>
                    @endif
                </div>
                <div class="progress bg-light" style="height: 6px;">
                    <div class="progress-bar bg-{{ $diskBadge }}" role="progressbar" style="width: {{ $diskUsage }}%"></div>
                </div>
            </div>
        </div>
    </div>
</div>