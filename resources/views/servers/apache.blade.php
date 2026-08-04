@extends('layouts.app')

@section('content')

<!-- Bootstrap Icons & Chart.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .page-header {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
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

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin-icon {
        display: inline-block;
        animation: spin 0.8s linear infinite;
    }
</style>

<!-- Header -->
<div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
        <h2 class="mb-1 fw-bold text-white"><i class="bi bi-server me-2"></i>Apache Monitoring</h2>
        <div class="text-white-50 opacity-75 fs-5 d-flex align-items-center gap-3 flex-wrap">
            <span>{{ $server->name }} (<code>{{ $server->hostname }}</code>)</span>
            <span class="d-inline-flex align-items-center gap-1 bg-white bg-opacity-10 px-2 py-1 rounded small text-white" id="live-indicator">
                <i class="bi bi-arrow-repeat" id="refresh-icon"></i>
                <span id="live-text"><span class="text-success-light fw-bold" style="color: #52c41a;">LIVE</span> • <span id="time-ago-text">Updated just now</span></span>
            </span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('servers.apache', $server) }}" class="btn btn-light btn-sm fw-semibold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </a>
        <a href="{{ route('servers.show', $server) }}" class="btn btn-outline-light btn-sm fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Main Partial Content Container -->
<div id="apache-main-content">
    @include('servers.partials.apache-content')
</div>

@endsection

@push('scripts')
<script>
let apacheTimelineChartInstance = null;

function renderApacheTimelineChart(labels, data) {
    const ctx = document.getElementById('apacheTimelineChart');
    if (!ctx) return;

    if (apacheTimelineChartInstance) {
        apacheTimelineChartInstance.destroy();
    }

    apacheTimelineChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{
                label: 'Requests / Min',
                data: data.length ? data : [0],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Requests: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const initialLabels = @json($metrics->requestTimeline['labels'] ?? []);
    const initialData = @json($metrics->requestTimeline['data'] ?? []);
    renderApacheTimelineChart(initialLabels, initialData);

    const refreshUrl = "{{ route('servers.apache.refresh', $server) }}";
    let lastSuccessTimestamp = Date.now();
    let isErrorState = false;

    function updateTimeAgo() {
        if (isErrorState) return;
        const timeAgoText = document.getElementById('time-ago-text');
        if (!timeAgoText) return;

        const elapsedSeconds = Math.floor((Date.now() - lastSuccessTimestamp) / 1000);
        if (elapsedSeconds <= 1) {
            timeAgoText.textContent = 'Updated just now';
        } else {
            timeAgoText.textContent = `Updated ${elapsedSeconds} sec ago`;
        }
    }

    setInterval(updateTimeAgo, 1000);

    async function fetchApacheData() {
        const refreshIcon = document.getElementById('refresh-icon');
        if (refreshIcon) refreshIcon.classList.add('spin-icon');

        try {
            const response = await fetch(refreshUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP status: ${response.status}`);
            }

            const data = await response.json();

            if (data.html && document.getElementById('apache-main-content')) {
                document.getElementById('apache-main-content').innerHTML = data.html;
            }

            if (data.metrics && data.metrics.requestTimeline) {
                renderApacheTimelineChart(
                    data.metrics.requestTimeline.labels || [],
                    data.metrics.requestTimeline.data || []
                );
            }

            lastSuccessTimestamp = Date.now();
            isErrorState = false;

            if (refreshIcon) refreshIcon.classList.remove('spin-icon');

            const liveText = document.getElementById('live-text');
            if (liveText) {
                liveText.innerHTML = '<span class="text-success-light fw-bold" style="color: #52c41a;">LIVE</span> • <span id="time-ago-text">Updated just now</span>';
            }

        } catch (error) {
            console.error('Auto-refresh Apache failed:', error);
            isErrorState = true;

            if (refreshIcon) refreshIcon.classList.remove('spin-icon');

            const liveText = document.getElementById('live-text');
            if (liveText) {
                liveText.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Update failed</span>';
            }
        }
    }

    // Auto Refresh every 30 seconds
    setInterval(fetchApacheData, 30000);
});
</script>
@endpush
