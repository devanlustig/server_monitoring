@extends('layouts.app')

@section('content')

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-radius: 12px;
        border: none;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    .table-middle td, .table-middle th {
        vertical-align: middle;
    }
    .query-code {
        font-family: monospace;
        font-size: 0.85rem;
        background-color: #f8f9fa;
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        word-break: break-all;
    }
    .page-header {
        background: linear-gradient(135deg, #0d6efd, #8e1592);
        color: white;
        padding: 2rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.15);
    }
    .badge-soft {
        background-opacity: 0.1;
    }
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2 class="mb-1 fw-bold text-white"><i class="bi bi-database-fill-gear me-2"></i>PostgreSQL Monitoring</h2>
        <div class="text-white-50 opacity-75 fs-5">
            {{ $server->name }}
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('servers.postgresql', $server) }}" class="btn btn-light btn-sm fw-semibold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </a>
        <a href="{{ route('servers.show', $server) }}" class="btn btn-outline-light btn-sm fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Connections</div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ $summary->currentConnections }} <span class="text-muted fs-5 fw-normal">/ {{ $summary->maxConnections }}</span>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Usage</div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ $summary->usagePercent }}%
                </h3>
                <div class="progress mt-3 bg-light" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $summary->usagePercent }}%" aria-valuenow="{{ $summary->usagePercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Active</div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-activity"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ $summary->activeConnections }}
                </h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Idle</div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-pause-circle-fill"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ $summary->idleConnections }}
                </h3>
            </div>
        </div>
    </div>
</div>

<!-- Quick Insights -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary stat-card">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2"><i class="bi bi-person-badge me-1 text-primary"></i> Top Client</div>
                @if($topClient->isNotEmpty())
                    <h5 class="mb-1 fw-bold text-dark text-truncate" title="{{ $topClient->keys()->first() }}">
                        {{ $topClient->keys()->first() }}
                    </h5>
                    <div class="small text-muted fw-medium">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ $topClient->first() }}</span> Connection(s)
                    </div>
                @else
                    <div class="text-muted fst-italic">No data</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info stat-card">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2"><i class="bi bi-window-stack me-1 text-info"></i> Top Application</div>
                @if($topApplication->isNotEmpty())
                    <h5 class="mb-1 fw-bold text-dark text-truncate" title="{{ $topApplication->keys()->first() }}">
                        {{ $topApplication->keys()->first() }}
                    </h5>
                    <div class="small text-muted fw-medium">
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill">{{ $topApplication->first() }}</span> Connection(s)
                    </div>
                @else
                    <div class="text-muted fst-italic">No data</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary stat-card">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2"><i class="bi bi-hourglass-top me-1 text-secondary"></i> Oldest Connection</div>
                @if($oldestConnection)
                    <h5 class="mb-1 fw-bold text-dark">
                        {{ $oldestConnection->connectionAgeLabel() }}
                    </h5>
                    <div class="small text-muted text-truncate fw-medium" title="{{ $oldestConnection->client }}">
                        {{ $oldestConnection->client }}
                    </div>
                @else
                    <div class="text-muted fst-italic">No data</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning stat-card">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2"><i class="bi bi-clock-history me-1 text-warning"></i> Longest Idle</div>
                @if($longestIdle)
                    <h5 class="mb-1 fw-bold text-dark">
                        {{ $longestIdle->activityDurationLabel() }}
                    </h5>
                    <div class="small text-muted fw-medium">
                        PID <span class="badge bg-light text-dark border">{{ $longestIdle->pid }}</span>
                    </div>
                @else
                    <div class="text-muted fst-italic">No data</div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Operations & Filters Row -->
<div class="row g-4 mb-4">
    <!-- Operations -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold"><i class="bi bi-tools text-primary me-2"></i>Operations</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    
                    <div class="p-3 border rounded bg-light">
                        <form method="POST" action="{{ route('servers.postgresql.killIdleOlder', $server) }}">
                            @csrf
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Kill Idle Older Than</h6>
                                    <small class="text-muted">Safely clear stale connections.</small>
                                </div>
                                <div class="fs-4">🟡</div>
                            </div>
                            <div class="input-group input-group-sm mt-3 shadow-sm">
                                <select class="form-select" name="minutes">
                                    <option value="30">30 Minutes</option>
                                    <option value="60">1 Hour</option>
                                    <option value="360">6 Hours</option>
                                    <option value="720">12 Hours</option>
                                </select>
                                <button class="btn btn-warning fw-semibold text-dark" onclick="return confirm('Terminate idle PostgreSQL connections?')">
                                    Execute
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('servers.postgresql.capture', $server) }}" class="flex-fill">
                            @csrf
                            <button class="btn btn-outline-info w-100 fw-semibold" onclick="return confirm('Capture PostgreSQL Incident?')">
                                <i class="bi bi-camera me-1"></i> Capture Incident
                            </button>
                        </form>
                        <form method="POST" action="{{ route('servers.postgresql.restart', $server) }}" class="flex-fill">
                            @csrf
                            <button class="btn btn-outline-danger w-100 fw-semibold" onclick="return confirm('Restart PostgreSQL Service?')">
                                <i class="bi bi-power me-1"></i> Restart DB
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold"><i class="bi bi-funnel-fill text-primary me-2"></i>Filter Connections</h5>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Client</label>
                            <select class="form-select shadow-none bg-light" name="client">
                                <option value="">All Clients</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client }}" @selected(request('client') == $client)>
                                        {{ $client }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Application</label>
                            <select class="form-select shadow-none bg-light" name="application">
                                <option value="">All Applications</option>
                                @foreach($applications as $application)
                                    <option value="{{ $application }}" @selected(request('application') == $application)>
                                        {{ $application }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Database</label>
                            <select class="form-select shadow-none bg-light" name="database">
                                <option value="">All Databases</option>
                                @foreach($databases as $database)
                                    <option value="{{ $database }}" @selected(request('database') == $database)>
                                        {{ $database }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">State</label>
                            <select class="form-select shadow-none bg-light" name="state">
                                <option value="">All States</option>
                                <option value="active" @selected(request('state') == 'active')>Active</option>
                                <option value="idle" @selected(request('state') == 'idle')>Idle</option>
                            </select>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <a href="{{ route('servers.postgresql', $server) }}" class="btn btn-light fw-semibold me-2">
                                Clear
                            </a>
                            <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm">
                                <i class="bi bi-filter"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Active Connections Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul text-primary me-2"></i>Active Connections</h5>
        <span class="badge bg-primary rounded-pill px-3 py-2 fs-6 shadow-sm">{{ count($connections) }} Found</span>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover table-middle mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="50">
                        <div class="form-check d-flex justify-content-center m-0">
                            <input class="form-check-input shadow-none m-0" type="checkbox" id="check-all">
                        </div>
                    </th>
                    <th class="text-secondary fw-semibold">PID</th>
                    <th class="text-secondary fw-semibold">Database</th>
                    <th class="text-secondary fw-semibold">User</th>
                    <th class="text-secondary fw-semibold">Application</th>
                    <th class="text-secondary fw-semibold">Client</th>
                    <th class="text-secondary fw-semibold">State</th>
                    <th class="text-secondary fw-semibold">Age</th>
                    <th class="text-secondary fw-semibold" width="160">
                        <a href="{{ request()->fullUrlWithQuery([
                                'sort' => 'activity_duration',
                                'direction' => request('direction') == 'asc' ? 'desc' : 'asc'
                            ]) }}"
                            class="text-decoration-none text-dark d-flex align-items-center gap-1">
                            Activity
                            @if(request('sort') == 'activity_duration')
                                <i class="bi bi-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                            @else
                                <i class="bi bi-arrow-down-up text-muted opacity-50"></i>
                            @endif
                        </a>
                    </th>
                    <th class="text-secondary fw-semibold" width="25%">Query</th>
                    <th class="text-center text-secondary fw-semibold" width="120">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($connections as $connection)
                <tr>
                    <td class="text-center">
                        @if($connection->canTerminate())
                            <div class="form-check d-flex justify-content-center m-0">
                                <input type="checkbox" class="form-check-input terminate-checkbox shadow-none m-0" value="{{ $connection->pid }}">
                            </div>
                        @endif
                    </td>
                    <td><span class="fw-bold text-dark">{{ $connection->pid }}</span></td>
                    <td>
                        <div class="fw-medium">{{ $connection->database }}</div>
                    </td>
                    <td>{{ $connection->user }}</td>
                    <td>
                        <div class="text-truncate text-muted" style="max-width: 120px;" title="{{ $connection->application }}">
                            {{ $connection->application ?: '-' }}
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate text-muted" style="max-width: 120px;" title="{{ $connection->client }}">
                            {{ $connection->client }}
                        </div>
                    </td>
                    <td>
                        <span class="badge rounded-pill bg-{{ $connection->stateBadgeColor() }} bg-opacity-10 text-{{ $connection->stateBadgeColor() == 'light' ? 'dark' : $connection->stateBadgeColor() }} border border-{{ $connection->stateBadgeColor() }} border-opacity-25 px-2 py-1">
                            <i class="bi bi-circle-fill small me-1 opacity-75" style="font-size: 0.5rem; vertical-align: middle;"></i> {{ strtoupper($connection->state) }}
                        </span>
                    </td>
                    <td>
                        <div class="small fw-bold text-dark">{{ $connection->connectionAgeLabel() }}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">{{ $connection->backendStart }}</div>
                    </td>
                    <td>
                        <span class="badge rounded-pill bg-{{ $connection->activityBadgeColor() }} px-2 py-1 shadow-sm">
                            @if($connection->state == 'active')
                                <i class="bi bi-lightning-charge-fill me-1 text-warning"></i> Running
                            @else
                                <i class="bi bi-moon-stars-fill me-1"></i> Idle
                            @endif
                            {{ $connection->activityDurationLabel() }}
                        </span>
                    </td>
                    <td>
                        <div class="query-code text-muted">
                            {{ Str::limit($connection->query, 80) }}
                        </div>
                    </td>
                    <td class="text-center">
                        @if($connection->canTerminate())
                            <form method="POST" action="{{ route('servers.postgresql.terminate', [$server, $connection->pid]) }}" class="m-0">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="return confirm('Terminate connection PID {{ $connection->pid }}?')">
                                    Kill
                                </button>
                            </form>
                        @else
                            <span class="badge bg-secondary rounded-pill px-3 py-2 bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                <i class="bi bi-shield-lock me-1"></i> Protected
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                            <h5 class="fw-bold">No PostgreSQL Connections</h5>
                            <p class="mb-0">There are no active connections matching your criteria.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if(count($connections) > 0)
    <div class="card-footer bg-light py-3 border-top d-flex align-items-center">
        <form id="bulkTerminateForm" method="POST" action="{{ route('servers.postgresql.killSelected', $server) }}" class="m-0">
            @csrf
            <div id="selectedPids"></div>
            <button type="submit" class="btn btn-danger fw-bold shadow-sm rounded-pill px-4" onclick="return prepareTerminate()">
                <i class="bi bi-x-octagon-fill me-1"></i> Terminate Selected
            </button>
        </form>
        <span class="text-muted ms-3 small fw-medium" id="selectedCount">0 connections selected</span>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('check-all');
    const terminateCheckboxes = document.querySelectorAll('.terminate-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    
    function updateSelectedCount() {
        if (!selectedCount) return;
        const count = document.querySelectorAll('.terminate-checkbox:checked').length;
        selectedCount.textContent = count + ' connection' + (count !== 1 ? 's' : '') + ' selected';
        
        if (count > 0) {
            selectedCount.classList.add('text-danger', 'fw-bold');
            selectedCount.classList.remove('text-muted');
        } else {
            selectedCount.classList.remove('text-danger', 'fw-bold');
            selectedCount.classList.add('text-muted');
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            terminateCheckboxes.forEach(function (checkbox) {
                checkbox.checked = checkAll.checked;
            });
            updateSelectedCount();
        });
    }

    terminateCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const all = terminateCheckboxes.length;
            const checked = document.querySelectorAll('.terminate-checkbox:checked').length;
            if (checkAll) {
                checkAll.checked = (all === checked && all > 0);
            }
            updateSelectedCount();
        });
    });
});

function prepareTerminate() {
    const container = document.getElementById('selectedPids');
    container.innerHTML = '';
    
    const checked = document.querySelectorAll('.terminate-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one connection to terminate.');
        return false;
    }
    
    checked.forEach(item => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'pids[]';
        input.value = item.value;
        container.appendChild(input);
    });
    
    return confirm('Are you sure you want to terminate ' + checked.length + ' selected connection(s)? This action cannot be undone.');
}
</script>
@endpush