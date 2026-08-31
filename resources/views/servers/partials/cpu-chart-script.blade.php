@php
    $cpuHistory = $server->cpuMetrics()->latest('collected_at')->limit(20)->get()->reverse();
    $chartLabels = $cpuHistory->map(fn($m) => $m->collected_at->format('H:i:s'))->values();
    $chartData = $cpuHistory->map(fn($m) => [
        'y' => (float)$m->usage_percent,
        'timestamp' => $m->collected_at->toDateTimeString()
    ])->values();
@endphp

@push('scripts')
<script>
let cpuChartInstance = null;
let currentAnalysisData = null; // Store fetched analysis details for node click interactions

function renderCpuChart(labels, data) {
    const ctx = document.getElementById('cpuChart');
    if (!ctx) return;

    if (cpuChartInstance) {
        cpuChartInstance.destroy();
    }

    const parsedData = data.map(d => typeof d === 'object' ? d.y : d);

    cpuChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{
                label: 'CPU Usage (%)',
                data: parsedData.length ? parsedData : [0],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const pointData = data[index];
                    if (pointData && pointData.timestamp) {
                        fetchCpuAnalysis(pointData.timestamp);
                    }
                }
            },
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

async function fetchCpuAnalysis(timestamp = '') {
    const analysisCard = document.getElementById('cpu-analysis-card');
    if (!analysisCard) return;

    analysisCard.style.display = 'block';
    
    // Show loading indicators
    document.getElementById('analysis-timestamp').textContent = 'Analyzing...';
    document.getElementById('summary-cause').textContent = 'Loading...';
    document.getElementById('summary-confidence').textContent = '-';
    document.getElementById('evidence-list').innerHTML = '<li class="list-group-item text-muted small py-2"><span class="spinner-border spinner-border-sm me-2"></span>Performing correlation analysis...</li>';

    try {
        const url = `{{ route('servers.cpu-analysis', $server) }}?timestamp=${encodeURIComponent(timestamp)}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to analyze');

        const data = await response.json();
        currentAnalysisData = data;

        // Update card headers & timestamp
        document.getElementById('analysis-timestamp').textContent = 'Timestamp: ' + data.timestamp;

        // Update CPU node
        document.getElementById('node-val-cpu').textContent = parseFloat(data.cpu).toFixed(1) + '%';
        const cpuNodeLbl = document.getElementById('node-lbl-cpu');
        if (data.cpu >= 80) {
            cpuNodeLbl.textContent = 'Critical Spike';
            cpuNodeLbl.className = 'text-danger fw-bold';
        } else if (data.cpu >= 50) {
            cpuNodeLbl.textContent = 'High Load';
            cpuNodeLbl.className = 'text-warning fw-bold';
        } else {
            cpuNodeLbl.textContent = 'Normal';
            cpuNodeLbl.className = 'text-success fw-bold';
        }

        // Update other nodes
        data.nodes.forEach(node => {
            if (node.id === 'cpu') return;

            const valEl = document.getElementById(`node-val-${node.id}`);
            const badgeEl = document.getElementById(`node-badge-${node.id}`);
            
            if (valEl) valEl.textContent = node.value;
            if (badgeEl) {
                badgeEl.textContent = 'Score: ' + Math.round(node.score);
                
                // Color badge based on correlation
                badgeEl.className = 'badge';
                if (node.score >= 80) badgeEl.classList.add('bg-danger');
                else if (node.score >= 60) badgeEl.classList.add('bg-warning', 'text-dark');
                else if (node.score >= 30) badgeEl.classList.add('bg-info', 'text-dark');
                else badgeEl.classList.add('bg-secondary');
            }
        });

        // Update Correlation Summary
        const causeEl = document.getElementById('summary-cause');
        causeEl.textContent = data.summary.primaryCause;
        causeEl.className = 'badge ';
        const maxScore = Math.max(...data.nodes.map(n => n.id !== 'cpu' ? n.score : 0));
        if (maxScore >= 80) causeEl.classList.add('bg-danger');
        else if (maxScore >= 60) causeEl.classList.add('bg-warning', 'text-dark');
        else if (maxScore >= 30) causeEl.classList.add('bg-info', 'text-dark');
        else causeEl.classList.add('bg-secondary');

        // Update confidence
        const confidenceEl = document.getElementById('summary-confidence');
        confidenceEl.textContent = data.summary.confidence;
        confidenceEl.className = 'fw-bold ';
        if (data.summary.confidence === 'Strong Correlation') confidenceEl.classList.add('text-danger');
        else if (data.summary.confidence === 'Likely Cause') confidenceEl.classList.add('text-warning');
        else if (data.summary.confidence === 'Contributing Factor') confidenceEl.classList.add('text-info');
        else confidenceEl.classList.add('text-secondary');

        // Update evidence list
        const evidenceContainer = document.getElementById('evidence-list');
        evidenceContainer.innerHTML = '';
        data.summary.evidence.forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item small py-2';
            li.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i>${item}`;
            evidenceContainer.appendChild(li);
        });

        // Show generic information in detail panel
        showNodeDetails('cpu');

    } catch (err) {
        console.error('Correlation analysis failed:', err);
        document.getElementById('analysis-timestamp').textContent = 'Error';
        document.getElementById('summary-cause').textContent = 'Analysis Failed';
        document.getElementById('evidence-list').innerHTML = '<li class="list-group-item text-danger small py-2"><i class="bi bi-x-circle-fill me-2"></i>Failed to execute correlation analysis. Please try again.</li>';
    }
}

function showNodeDetails(nodeId) {
    if (!currentAnalysisData) return;

    const node = currentAnalysisData.nodes.find(n => n.id === nodeId);
    if (!node) return;

    // Highlight selected node border
    document.querySelectorAll('.node-interactive').forEach(el => {
        el.classList.remove('border-primary', 'border-3');
    });
    const selectedNodeEl = document.querySelector(`.node-interactive[data-node="${nodeId}"]`);
    if (selectedNodeEl) {
        selectedNodeEl.classList.add('border-primary', 'border-3');
    }

    // Update Detail Panel Header
    document.getElementById('detail-panel-title').innerHTML = `<i class="bi bi-info-circle-fill me-1"></i>Detail: ${node.name}`;

    // Update Detail Evidence List with node-specific evidence
    const evidenceContainer = document.getElementById('evidence-list');
    evidenceContainer.innerHTML = '';

    const details = [
        `<strong>Metric Value:</strong> ${node.value}`,
        `<strong>Correlation Score:</strong> ${Math.round(node.score)} / 100`,
        `<strong>Assessment:</strong> ${node.score >= 80 ? 'Strong Correlation' : (node.score >= 60 ? 'Likely Cause' : (node.score >= 30 ? 'Contributing Factor' : 'No Evidence'))}`,
        `<strong>Diagnostic details:</strong> ${node.evidence}`
    ];

    details.forEach(item => {
        const li = document.createElement('li');
        li.className = 'list-group-item small py-2';
        li.innerHTML = item;
        evidenceContainer.appendChild(li);
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

    // Analyze current button trigger
    const btnAnalyzeCurrent = document.getElementById('btn-analyze-current');
    if (btnAnalyzeCurrent) {
        btnAnalyzeCurrent.addEventListener('click', function() {
            fetchCpuAnalysis();
        });
    }

    // Bind click events on all interactive nodes in node graph
    document.querySelectorAll('.node-interactive').forEach(el => {
        el.addEventListener('click', function() {
            const nodeId = this.getAttribute('data-node');
            showNodeDetails(nodeId);
        });
    });
});
</script>
@endpush
