<!-- Alerts -->
@if(!$metrics->logFound)
<div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-octagon-fill fs-3 me-3"></i>
        <div>
            <h6 class="fw-bold mb-1">Apache Access Log Not Found</h6>
            <div class="small">Unable to locate Apache access log file under standard paths (<code>/var/log/apache2/access.log</code> or <code>/var/log/httpd/access_log</code>). Please verify Apache installation on the target server.</div>
        </div>
    </div>
</div>
@elseif(!$metrics->hasResponseTime && $metrics->totalRequests > 0)
<div class="alert alert-warning shadow-sm border-0 mb-4" role="alert">
    <div class="d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
        <div>
            <h6 class="fw-bold mb-1">Response Time (%D / %T) Not Detected in Apache LogFormat</h6>
            <div class="small">To enable Average Response Time, Maximum Response Time, and Slow Endpoint analytics, please add <code>%D</code> (microseconds) to your Apache <code>LogFormat</code> configuration (e.g. in <code>/etc/apache2/apache2.conf</code> or <code>/etc/httpd/conf/httpd.conf</code>).</div>
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
                    <div class="stat-icon bg-{{ $metrics->healthScore >= 90 ? 'success' : ($metrics->healthScore >= 75 ? 'warning' : 'danger') }} bg-opacity-10 text-{{ $metrics->healthScore >= 90 ? 'success' : ($metrics->healthScore >= 75 ? 'warning' : 'danger') }}">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark">{{ number_format($metrics->healthScore, 1) }}%</h3>
                    <span class="badge bg-{{ $metrics->healthScore >= 90 ? 'success' : ($metrics->healthScore >= 75 ? 'warning' : 'danger') }} bg-opacity-10 text-{{ $metrics->healthScore >= 90 ? 'success' : ($metrics->healthScore >= 75 ? 'warning' : 'danger') }} border border-{{ $metrics->healthScore >= 90 ? 'success' : ($metrics->healthScore >= 75 ? 'warning' : 'danger') }} border-opacity-25 px-2 py-1">
                        {{ $metrics->healthScore >= 90 ? 'Excellent' : ($metrics->healthScore >= 75 ? 'Fair' : 'Critical') }}
                    </span>
                </div>
                <div class="text-muted small mt-2">
                    Evaluated from Error Rate & Slow Requests
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
                    <h3 class="fw-bold mb-0 text-success">{{ number_format($metrics->successRate, 1) }}%</h3>
                    <span class="text-muted small">Success</span>
                </div>
                <div class="text-muted small mt-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-circle text-danger me-1"></i> Error Rate:</span>
                    <span class="fw-bold text-{{ $metrics->errorRate > 5 ? 'danger' : 'dark' }}">{{ number_format($metrics->errorRate, 1) }}%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Slow Request Count -->
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Slow Requests (&gt;500ms)</div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">{{ number_format($metrics->slowRequestCount) }}</h3>
                <div class="text-muted small mt-2">
                    <i class="bi bi-info-circle me-1"></i> {{ number_format($metrics->responseTimeDistribution['over1000ms']) }} requests &gt; 1 sec
                </div>
            </div>
        </div>
    </div>

    <!-- Peak & Avg Request Volume -->
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Peak Minute Volume</div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">{{ number_format($metrics->peakRequestMinute['count']) }} <span class="fs-6 fw-normal text-muted">req</span></h3>
                <div class="text-muted small mt-2 d-flex justify-content-between">
                    <span><i class="bi bi-clock me-1"></i> Peak at {{ $metrics->peakRequestMinute['minute'] }}</span>
                    <span>Avg: {{ number_format($metrics->averageRequestMinute, 1) }}/m</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards Row -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Total Requests</div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-card-list"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ number_format($metrics->totalRequests) }}
                </h3>
                <div class="text-muted small mt-2">
                    <i class="bi bi-speedometer2 me-1"></i> {{ number_format($metrics->requestsPerMinute, 1) }} req/min ({{ number_format($metrics->requestsPerHour, 0) }}/h)
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Total Traffic</div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-arrow-down-up"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ $metrics->formattedTotalTraffic() }}
                </h3>
                <div class="text-muted small mt-2">
                    Transferred across {{ number_format($metrics->totalRequests) }} requests
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">Avg Response Time</div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark">
                    {{ $metrics->hasResponseTime ? number_format($metrics->averageResponseTimeMs, 1) . ' ms' : 'N/A' }}
                </h3>
                <div class="text-muted small mt-2">
                    @if($metrics->hasResponseTime)
                        Based on logged <code>%D</code> timing
                    @else
                        Requires <code>%D</code> in LogFormat
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100 bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="text-muted fw-bold text-uppercase small tracking-wide">HTTP Status Breakdown</div>
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">2xx: {{ number_format($metrics->http2xx) }}</span>
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">3xx: {{ number_format($metrics->http3xx) }}</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">4xx: {{ number_format($metrics->http4xx) }}</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">5xx: {{ number_format($metrics->http5xx) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recommendations Card -->
@if(!empty($metrics->recommendations))
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-lightbulb-fill text-warning me-2"></i>System Recommendations & Insights</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($metrics->recommendations as $rec)
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-{{ $rec['type'] }} bg-opacity-10 border-{{ $rec['type'] }} border-opacity-25 h-100 d-flex align-items-start gap-3">
                            <i class="bi {{ $rec['icon'] }} fs-4 text-{{ $rec['type'] }} flex-shrink-0 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">{{ $rec['title'] }}</h6>
                                <div class="small text-secondary">{!! $rec['message'] !!}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Response Time Distribution -->
@if($metrics->hasResponseTime)
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-steps text-primary me-2"></i>Response Time Distribution</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-success fw-bold small text-uppercase mb-1">&lt; 100 ms</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics->responseTimeDistribution['under100ms']) }}</h4>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-primary fw-bold small text-uppercase mb-1">100 - 300 ms</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics->responseTimeDistribution['between100and300ms']) }}</h4>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-info fw-bold small text-uppercase mb-1">300 - 500 ms</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics->responseTimeDistribution['between300and500ms']) }}</h4>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-warning fw-bold small text-uppercase mb-1">500 - 1000 ms</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics->responseTimeDistribution['between500and1000ms']) }}</h4>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 border rounded bg-light">
                            <div class="text-danger fw-bold small text-uppercase mb-1">&gt; 1000 ms</div>
                            <h4 class="fw-bold mb-0 text-dark">{{ number_format($metrics->responseTimeDistribution['over1000ms']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Charts Row: Timeline & HTTP Status Pie Chart -->
<div class="row g-4 mb-4">
    <!-- Request Timeline Line Chart -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Request Timeline (Req / Min)</h5>
                <span class="badge bg-light text-muted border">Realtime Log Sample</span>
            </div>
            <div class="card-body">
                <div style="height: 240px; position: relative;">
                    <canvas id="apacheTimelineChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- HTTP Status Code Pie Chart -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
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

<!-- Tables Row 1: Top Endpoints & Slow Endpoints -->
<div class="row g-4 mb-4">
    <!-- Top Endpoints Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-trophy text-primary me-2"></i>Top Endpoints</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Endpoint</th>
                            <th class="text-center text-secondary fw-semibold">Requests</th>
                            <th class="text-end text-secondary fw-semibold">Avg Response</th>
                            <th class="text-end text-secondary fw-semibold">Max Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics->topEndpoints as $ep)
                        <tr>
                            <td>
                                <code class="text-primary fw-medium" title="{{ $ep['endpoint'] }}">{{ Str::limit($ep['endpoint'], 35) }}</code>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">{{ number_format($ep['requests']) }}</span>
                            </td>
                            <td class="text-end fw-medium text-dark">
                                {{ $ep['avgResponseMs'] !== null ? number_format($ep['avgResponseMs'], 1) . ' ms' : '-' }}
                            </td>
                            <td class="text-end fw-medium text-muted">
                                {{ $ep['maxResponseMs'] !== null ? number_format($ep['maxResponseMs'], 1) . ' ms' : '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No endpoint data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Slow Endpoints Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold"><i class="bi bi-hourglass-bottom text-warning me-2"></i>Slowest Requests</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold">Endpoint</th>
                            <th class="text-center text-secondary fw-semibold">Method</th>
                            <th class="text-end text-secondary fw-semibold">Response Time</th>
                            <th class="text-center text-secondary fw-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics->slowEndpoints as $slow)
                        @php
                            $rt = $slow['responseTimeMs'];
                            $badgeClass = 'bg-secondary text-dark';
                            if ($rt !== null) {
                                if ($rt < 300) {
                                    $badgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                                } elseif ($rt <= 500) {
                                    $badgeClass = 'bg-info bg-opacity-10 text-info border border-info border-opacity-25';
                                } elseif ($rt <= 1000) {
                                    $badgeClass = 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25';
                                } else {
                                    $badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                                }
                            }
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-medium text-truncate" style="max-width: 220px;" title="{{ $slow['endpoint'] }}">
                                    <code>{{ $slow['endpoint'] }}</code>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">IP: {{ $slow['ip'] }} • {{ $slow['timestamp'] }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary bg-opacity-10 text-dark border">{{ $slow['method'] }}</span>
                            </td>
                            <td class="text-end fw-bold">
                                <span class="badge {{ $badgeClass }} px-2 py-1 fs-6">
                                    {{ number_format($slow['responseTimeMs'], 1) }} ms
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $slow['statusCode'] < 400 ? 'success' : ($slow['statusCode'] < 500 ? 'warning' : 'danger') }} bg-opacity-10 text-dark border">
                                    {{ $slow['statusCode'] }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                @if(!$metrics->hasResponseTime)
                                    Response time logging (<code>%D</code>) disabled in Apache
                                @else
                                    No slow request recorded
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row 2: Top Client IPs & Error Endpoints -->
<div class="row g-4 mb-4">
    <!-- Top Client IPs Table -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
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
                        @forelse($metrics->topClientIps as $client)
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
        <div class="card shadow-sm border-0 h-100">
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
                        @forelse($metrics->errorEndpoints as $err)
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

