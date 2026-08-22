<!-- Alerts -->
@if(!$metrics['logFound'])
<div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-octagon-fill fs-3 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Nginx Access Log Not Found</h6>
            <div class="small">Unable to locate Nginx access log file under standard path (<code>/var/log/nginx/access.log</code>). Please verify Nginx installation on the target server.</div>
        </div>
    </div>
</div>
@endif

<!-- Row 0: Health Score & High Level Analytics Cards -->
<div class="row g-4 mb-4">
    <!-- Health Score -->
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Health Score</div>
                    <div class="stat-icon bg-{{ $metrics['healthScore'] >= 90 ? 'success' : ($metrics['healthScore'] >= 75 ? 'warning' : 'danger') }} bg-opacity-10 text-{{ $metrics['healthScore'] >= 90 ? 'success' : ($metrics['healthScore'] >= 75 ? 'warning' : 'danger') }}">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark">{{ number_format($metrics['healthScore'], 1) }}%</h3>
                    <span class="badge bg-{{ $metrics['healthScore'] >= 90 ? 'success' : ($metrics['healthScore'] >= 75 ? 'warning' : 'danger') }} bg-opacity-10 text-{{ $metrics['healthScore'] >= 90 ? 'success' : ($metrics['healthScore'] >= 75 ? 'warning' : 'danger') }} border border-{{ $metrics['healthScore'] >= 90 ? 'success' : ($metrics['healthScore'] >= 75 ? 'warning' : 'danger') }} border-opacity-25 px-2 py-1">
                        {{ $metrics['healthScore'] >= 90 ? 'Excellent' : ($metrics['healthScore'] >= 75 ? 'Fair' : 'Critical') }}
                    </span>
                </div>
                <div class="text-muted small mt-2">
                    Evaluated from Error Rate
                </div>
            </div>
        </div>
    </div>

    <!-- Error & Success Rate -->
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Error & Success Rate</div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-success">{{ number_format($metrics['successRate'], 2) }}%</h3>
                    <span class="text-muted small">Success</span>
                </div>
                <div class="text-muted small mt-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-circle text-danger me-1"></i> Error Rate:</span>
                    <span class="fw-bold text-{{ $metrics['errorRate'] > 5 ? 'danger' : 'dark' }}">{{ number_format($metrics['errorRate'], 2) }}%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Requests Volume -->
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Requests Rate</div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">{{ number_format($metrics['requestsPerMinute'], 1) }} <span class="fs-6 fw-normal text-muted">req/m</span></h3>
                <div class="text-muted small mt-2">
                    Avg: {{ number_format($metrics['requestsPerHour']) }}/h
                </div>
            </div>
        </div>
    </div>

    <!-- Total Traffic -->
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Total Traffic</div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-arrow-down-up"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">{{ $traffic }}</h3>
                <div class="text-muted small mt-2">
                    Across {{ number_format($metrics['totalRequests']) }} Requests
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTTP Status Breakdown -->
<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted fw-bold text-uppercase small tracking-wide">HTTP Status Breakdown</div>
            <span class="badge bg-light text-dark border">Breakdown</span>
        </div>
        <div class="row text-center g-3">
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded bg-light">
                    <div class="text-success fw-bold small text-uppercase mb-1">2xx Success</div>
                    <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics['http2xx']) }}</h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded bg-light">
                    <div class="text-info fw-bold small text-uppercase mb-1">3xx Redirect</div>
                    <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics['http3xx']) }}</h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded bg-light">
                    <div class="text-warning fw-bold small text-uppercase mb-1">4xx Client Error</div>
                    <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics['http4xx']) }}</h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded bg-light">
                    <div class="text-danger fw-bold small text-uppercase mb-1">5xx Server Error</div>
                    <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics['http5xx']) }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row: Timeline & HTTP Status Pie Chart -->
<div class="row g-4 mb-4">
    <!-- Request Timeline Line Chart -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100 bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Request Timeline (Req / Min)</h5>
                <span class="badge bg-light text-muted border">Realtime Log Sample</span>
            </div>
            <div class="card-body">
                <div style="height: 240px; position: relative;">
                    <canvas id="nginxTimelineChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- HTTP Status Code Pie Chart -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100 bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart text-primary me-2"></i>HTTP Status Pie Chart</h5>
                <span class="badge bg-light text-muted border">Breakdown</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="height: 240px; width: 100%; position: relative;">
                    <canvas id="httpStatusPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Endpoint Performance Table -->
<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>
                Endpoint Performance
            </h5>
            <small class="text-muted">
                Endpoint analytics based on Nginx access log
            </small>
        </div>
        <span class="badge bg-primary">
            {{ count($metrics['topEndpoints']) }} Endpoints
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Endpoint</th>
                        <th class="text-center">Requests</th>
                        <th class="text-center">Traffic</th>
                        <th class="text-center">2xx</th>
                        <th class="text-center">3xx</th>
                        <th class="text-center">4xx</th>
                        <th class="text-center">5xx</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metrics['topEndpoints'] as $endpoint)
                        @php
                            $error = $endpoint['5xx'] ?? 0;
                            $clientError = $endpoint['4xx'] ?? 0;
                            if ($error > 0) {
                                $badge = 'danger';
                                $text = 'Critical';
                            } elseif ($clientError > 5) {
                                $badge = 'warning';
                                $text = 'Warning';
                            } else {
                                $badge = 'success';
                                $text = 'Healthy';
                            }
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <code>{{ $endpoint['endpoint'] }}</code>
                                </div>
                            </td>
                            <td class="text-center">
                                {{ number_format($endpoint['requests']) }}
                            </td>
                            <td class="text-center">
                                {{ number_format(($endpoint['bytes'] ?? 0) / 1024 / 1024, 2) }} MB
                            </td>
                            <td class="text-center text-success">
                                {{ number_format($endpoint['2xx']) }}
                            </td>
                            <td class="text-center text-info">
                                {{ number_format($endpoint['3xx']) }}
                            </td>
                            <td class="text-center text-warning">
                                {{ number_format($endpoint['4xx']) }}
                            </td>
                            <td class="text-center text-danger">
                                {{ number_format($endpoint['5xx']) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $badge }}">
                                    {{ $text }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No endpoint data available</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Row 2: Top Client IPs & Error Endpoints -->
<div class="row g-4 mb-4">
    <!-- Top Client IPs Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill text-info me-2"></i>Top Client IPs</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Client IP</th>
                            <th class="text-center text-secondary fw-semibold">Requests</th>
                            <th class="text-end text-secondary fw-semibold">Bandwidth</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics['topClientIps'] as $client)
                        <tr>
                            <td>
                                <span class="fw-bold text-dark"><i class="bi bi-pc me-1 text-muted"></i> {{ $client['ip'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3 py-1">{{ number_format($client['requests']) }}</span>
                            </td>
                            <td class="text-end fw-medium text-dark">
                                @php
                                    $b = $client['totalBytes'];
                                    $bFormatted = $b >= 1048576 ? number_format($b / 1048576, 2).' MB' : ($b >= 1024 ? number_format($b / 1024, 2).' KB' : $b.' B');
                                @endphp
                                {{ $bFormatted }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No client IP data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Error Endpoints Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Error Endpoints</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Endpoint</th>
                            <th class="text-center text-secondary fw-semibold">404</th>
                            <th class="text-center text-secondary fw-semibold">500</th>
                            <th class="text-center text-secondary fw-semibold">503</th>
                            <th class="text-end text-secondary fw-semibold">Total Errors</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics['errorEndpoints'] as $err)
                        <tr>
                            <td>
                                <code class="text-danger fw-medium" title="{{ $err['endpoint'] }}">{{ Str::limit($err['endpoint'], 30) }}</code>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-warning text-dark bg-opacity-25">{{ $err['status404'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger bg-opacity-25 text-danger">{{ $err['status500'] }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger bg-opacity-25 text-danger">{{ $err['status503'] }}</span>
                            </td>
                            <td class="text-end fw-bold text-danger">
                                {{ number_format($err['totalErrors']) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-check-circle-fill text-success me-1"></i> No HTTP errors recorded
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
