@php
    $cpuHistory = $server->cpuMetrics()->latest('collected_at')->limit(20)->get()->reverse();
    $chartLabels = $cpuHistory->map(fn($m) => $m->collected_at->format('H:i:s'))->values();
    $chartData = $cpuHistory->map(fn($m) => $m->usage_percent)->values();
@endphp

@push('scripts')
<script>
let cpuChartInstance = null;

function renderCpuChart(labels, data) {
    const ctx = document.getElementById('cpuChart');
    if (!ctx) return;

    if (cpuChartInstance) {
        cpuChartInstance.destroy();
    }

    cpuChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{
                label: 'CPU Usage (%)',
                data: data.length ? data : [0],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
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
                        label: function(context) { return 'Usage: ' + context.parsed.y + '%'; }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: function(value) { return value + '%'; } },
                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const initialLabels = @json($chartLabels);
    const initialData = @json($chartData);
    renderCpuChart(initialLabels, initialData);

    const refreshUrl = "{{ route('servers.refresh', $server) }}";

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

    // Update time-ago ticker every second
    setInterval(updateTimeAgo, 1000);

    async function fetchDashboardPartial() {
        const refreshIcon = document.getElementById('refresh-icon');
        const liveText = document.getElementById('live-text');

        if (refreshIcon) {
            refreshIcon.classList.add('spin-icon');
        }

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

            // Update Partials HTML
            if (data.header && document.getElementById('partial-header')) {
                document.getElementById('partial-header').innerHTML = data.header;
            }
            if (data.system_information && document.getElementById('partial-system-information')) {
                document.getElementById('partial-system-information').innerHTML = data.system_information;
            }
            if (data.cpu_detail && document.getElementById('partial-cpu-detail')) {
                document.getElementById('partial-cpu-detail').innerHTML = data.cpu_detail;
            }
            if (data.memory_detail && document.getElementById('partial-memory-detail')) {
                document.getElementById('partial-memory-detail').innerHTML = data.memory_detail;
            }
            if (data.disk_detail && document.getElementById('partial-disk-detail')) {
                document.getElementById('partial-disk-detail').innerHTML = data.disk_detail;
            }
            if (data.cpu_chart && document.getElementById('partial-cpu-chart')) {
                document.getElementById('partial-cpu-chart').innerHTML = data.cpu_chart;
            }

            // Update Chart.js Instance
            if (data.chart_labels && data.chart_data) {
                renderCpuChart(data.chart_labels, data.chart_data);
            }

            // Success Visual Feedback
            lastSuccessTimestamp = Date.now();
            isErrorState = false;

            const updatedRefreshIcon = document.getElementById('refresh-icon');
            if (updatedRefreshIcon) {
                updatedRefreshIcon.classList.remove('spin-icon');
            }

            const updatedLiveText = document.getElementById('live-text');
            if (updatedLiveText) {
                updatedLiveText.innerHTML = '<span class="text-success-light fw-bold" style="color: #52c41a;">LIVE</span> • <span id="time-ago-text">Updated just now</span>';
            }

            const lastCheckedEl = document.getElementById('last-checked-time');
            if (lastCheckedEl) {
                lastCheckedEl.classList.add('highlight-fade');
                setTimeout(() => lastCheckedEl.classList.remove('highlight-fade'), 850);
            }

            const statusBadgeEl = document.getElementById('status-badge');
            if (statusBadgeEl) {
                statusBadgeEl.classList.add('pulse-once');
                setTimeout(() => statusBadgeEl.classList.remove('pulse-once'), 550);
            }

        } catch (error) {
            console.error('Auto-refresh dashboard failed:', error);
            isErrorState = true;

            const updatedRefreshIcon = document.getElementById('refresh-icon');
            if (updatedRefreshIcon) {
                updatedRefreshIcon.classList.remove('spin-icon');
            }

            const updatedLiveText = document.getElementById('live-text');
            if (updatedLiveText) {
                updatedLiveText.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Update failed</span>';
            }
        }
    }

    // Auto refresh interval 31 seconds
    setInterval(fetchDashboardPartial, 31000);
});
</script>
@endpush
