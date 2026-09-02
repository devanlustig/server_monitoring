let TimelineChartInstance = null;
let httpStatusPieChartInstance = null;
let historyChart = null;
let nginxTimelineChartInstance = null;
let currentHistoryTimestamps = [];
let currentAnalysisEndpoints = [];

function renderNginxTimelineChart(labels, data) {
    const ctx = document.getElementById('nginxTimelineChart');
    if (!ctx) return;

    if (nginxTimelineChartInstance) {
        nginxTimelineChartInstance.destroy();
    }

    nginxTimelineChartInstance = new Chart(ctx, {
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

function renderHistoryChart(labels, values, timestamps = []) {
    const canvas = document.getElementById('historyResponseChart');
    if (!canvas) return;

    const emptyState = document.getElementById('historyChartEmptyState');

    if (historyChart) {
        historyChart.destroy();
    }

    currentHistoryTimestamps = timestamps;

    if (!values || values.length === 0) {
        if (emptyState) emptyState.classList.remove('d-none');
        canvas.style.display = 'none';
        return;
    }

    if (emptyState) emptyState.classList.add('d-none');
    canvas.style.display = 'block';

    historyChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Value',
                data: values,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, .15)',
                fill: true,
                tension: .35,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: '#198754'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const timestamp = currentHistoryTimestamps && currentHistoryTimestamps[index]
                        ? currentHistoryTimestamps[index]
                        : (labels[index] || '');
                    if (timestamp) {
                        fetchRequestAnalysis(timestamp);
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        afterBody: function() {
                            return '(Click point to analyze endpoints)';
                        }
                    }
                }
            }
        }
    });
}

function renderHttpStatusPieChart(http2xx, http3xx, http4xx, http5xx) {
    const ctx = document.getElementById('httpStatusPieChart');
    if (!ctx) return;

    if (httpStatusPieChartInstance) {
        httpStatusPieChartInstance.destroy();
    }

    httpStatusPieChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['2xx Success', '3xx Redirection', '4xx Client Error', '5xx Server Error'],
            datasets: [{
                data: [http2xx, http3xx, http4xx, http5xx],
                backgroundColor: ['#198754', '#0dcaf0', '#ffc107', '#dc3545'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        }
    });
}

async function fetchRequestAnalysis(timestamp) {
    const container = document.getElementById('nginx-request-analysis-container');
    if (!container) return;

    container.style.display = 'block';

    const loadingEl = document.getElementById('nginx-analysis-loading');
    const emptyEl = document.getElementById('nginx-analysis-empty');
    const contentEl = document.getElementById('nginx-analysis-content');
    const tsEl = document.getElementById('nginx-analysis-timestamp');

    if (tsEl) tsEl.textContent = timestamp;
    if (loadingEl) loadingEl.classList.remove('d-none');
    if (emptyEl) emptyEl.classList.add('d-none');
    if (contentEl) contentEl.classList.add('d-none');

    try {
        const url = window.nginxConfig.requestAnalysisUrl + '?timestamp=' + encodeURIComponent(timestamp);
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error('Analysis request failed');

        const json = await response.json();
        currentAnalysisEndpoints = json.endpoints || [];

        if (loadingEl) loadingEl.classList.add('d-none');

        if (!json.endpoints || json.endpoints.length === 0) {
            if (emptyEl) emptyEl.classList.remove('d-none');
            document.getElementById('nginx-analysis-total-req').textContent = '0';
            document.getElementById('nginx-analysis-active-ep').textContent = '0';
            return;
        }

        if (contentEl) contentEl.classList.remove('d-none');

        document.getElementById('nginx-analysis-total-req').textContent = json.totalRequests;
        document.getElementById('nginx-analysis-active-ep').textContent = json.endpoints.length;

        document.getElementById('nginx-parent-req-val').textContent = json.totalRequests;
        document.getElementById('nginx-parent-req-rate').textContent = json.requestsPerMinute + ' req/min';

        const nodesList = document.getElementById('nginx-endpoint-nodes-list');
        nodesList.innerHTML = '';

        let firstNodeEl = null;

        json.endpoints.forEach((ep, idx) => {
            let badgeBg = 'bg-success';
            if (ep.http5xx > 0) badgeBg = 'bg-danger';
            else if (ep.http4xx > 0) badgeBg = 'bg-warning text-dark';
            else if (ep.http3xx > 0) badgeBg = 'bg-info text-dark';

            const card = document.createElement('div');
            card.className = 'card text-center shadow-sm ep-node-card';
            card.style.cssText = 'width: 140px; cursor: pointer; transition: transform 0.15s ease; border: 1px solid #dee2e6;';
            card.innerHTML = `
                <div class="card-header bg-light py-1 small fw-bold text-truncate" title="${ep.endpoint}">${ep.endpoint}</div>
                <div class="card-body py-2 px-1">
                    <h6 class="mb-1 fw-bold text-dark text-truncate">${ep.requests} req</h6>
                    <span class="badge ${badgeBg}">${ep.percentage}%</span>
                </div>
            `;

            card.addEventListener('click', () => renderEndpointDetails(ep, card));
            nodesList.appendChild(card);

            if (idx === 0) firstNodeEl = card;
        });

        if (json.endpoints.length > 0 && firstNodeEl) {
            renderEndpointDetails(json.endpoints[0], firstNodeEl);
        }

    } catch (err) {
        console.error('Nginx Request Analysis Error:', err);
        if (loadingEl) loadingEl.classList.add('d-none');
        if (emptyEl) emptyEl.classList.remove('d-none');
    }
}

function renderEndpointDetails(ep, cardEl) {
    document.querySelectorAll('#nginx-endpoint-nodes-list .ep-node-card').forEach(c => {
        c.classList.remove('border-primary', 'border-3');
    });

    if (cardEl) {
        cardEl.classList.add('border-primary', 'border-3');
    }

    const badgeEl = document.getElementById('nginx-ep-detail-badge');
    if (badgeEl) badgeEl.textContent = ep.percentage + '% of Total';

    const bodyEl = document.getElementById('nginx-ep-detail-body');
    if (!bodyEl) return;

    let sourceBadgeBg = 'bg-secondary';
    if (ep.sourceType === 'proxy') sourceBadgeBg = 'bg-primary';
    else if (ep.sourceType === 'alias') sourceBadgeBg = 'bg-info text-dark';

    bodyEl.innerHTML = `
        <div class="mb-3">
            <span class="text-muted small d-block fw-semibold mb-1">Target Endpoint</span>
            <code class="fw-bold fs-6 text-break text-primary p-2 bg-light rounded d-block">${ep.endpoint}</code>
        </div>
        
        <div class="row g-2 mb-3 text-center">
            <div class="col-3">
                <div class="p-2 border rounded bg-success bg-opacity-10">
                    <span class="small text-muted d-block">2xx</span>
                    <strong class="text-success">${ep.http2xx}</strong>
                </div>
            </div>
            <div class="col-3">
                <div class="p-2 border rounded bg-info bg-opacity-10">
                    <span class="small text-muted d-block">3xx</span>
                    <strong class="text-info">${ep.http3xx}</strong>
                </div>
            </div>
            <div class="col-3">
                <div class="p-2 border rounded bg-warning bg-opacity-10">
                    <span class="small text-muted d-block">4xx</span>
                    <strong class="text-warning">${ep.http4xx}</strong>
                </div>
            </div>
            <div class="col-3">
                <div class="p-2 border rounded bg-danger bg-opacity-10">
                    <span class="small text-muted d-block">5xx</span>
                    <strong class="text-danger">${ep.http5xx}</strong>
                </div>
            </div>
        </div>

        <div class="p-3 border rounded bg-light">
            <span class="text-muted small d-block fw-semibold mb-2"><i class="bi bi-folder-symlink me-1"></i>Source & Application Resolution</span>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge ${sourceBadgeBg}">${ep.sourceType.toUpperCase()}</span>
                <span class="small text-dark fw-semibold text-break">${ep.source}</span>
            </div>
            <div class="small text-muted">Application Target: <code class="text-dark">${ep.application}</code></div>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', function () {
    const historyUrl = window.nginxConfig.historyUrl;
    const initialLabels = window.nginxConfig.initialTimeline ? window.nginxConfig.initialTimeline.labels : [];
    const initialData = window.nginxConfig.initialTimeline ? window.nginxConfig.initialTimeline.data : [];
    
    if (document.getElementById('nginxTimelineChart')) {
        renderNginxTimelineChart(initialLabels, initialData);
    }

    renderHistoryChart(
        window.nginxConfig.initialHistory.labels,
        window.nginxConfig.initialHistory.values,
        window.nginxConfig.initialHistory.timestamps || []
    );

    renderHttpStatusPieChart(
        window.nginxConfig.httpStatus.success,
        window.nginxConfig.httpStatus.redirect,
        window.nginxConfig.httpStatus.client,
        window.nginxConfig.httpStatus.server
    );

    bindHistoryEvents();

    const closeBtn = document.getElementById('nginx-close-analysis-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            const container = document.getElementById('nginx-request-analysis-container');
            if (container) container.style.display = 'none';
        });
    }

    const refreshUrl = window.nginxConfig.refreshUrl;
    let lastSuccessTimestamp = Date.now();
    let isErrorState = false;

    async function loadHistory(period, metric) {
        const response = await fetch(historyUrl + '?period=' + period + '&metric=' + metric, {
            headers: {
                'Accept': 'application/json'
            }
        });

        const json = await response.json();
        document.getElementById('summaryCurrent').innerHTML = json.summary.current.toFixed(1);
        document.getElementById('summaryAverage').innerHTML = json.summary.average.toFixed(1);
        document.getElementById('summaryMaximum').innerHTML = json.summary.maximum.toFixed(1);
        document.getElementById('summaryMinimum').innerHTML = json.summary.minimum.toFixed(1);
        renderHistoryChart(
            json.chart.labels,
            json.chart.values,
            json.chart.timestamps || []
        );
    }

    function bindHistoryEvents() {
        const period = document.getElementById('historyPeriod');
        const metric = document.getElementById('historyMetric');

        if (period) {
            period.onchange = function () {
                loadHistory(this.value, metric.value);
            };
        }

        if (metric) {
            metric.onchange = function () {
                loadHistory(period.value, this.value);
            };
        }
    }

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

    async function fetchNginxData() {
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

            if (data.html && document.getElementById('nginx-live-content')) {
                document.getElementById('nginx-live-content').innerHTML = data.html;
            }

            if (data.metrics) {
                if (data.metrics.requestTimeline && document.getElementById('nginxTimelineChart')) {
                    renderNginxTimelineChart(
                        data.metrics.requestTimeline.labels || [],
                        data.metrics.requestTimeline.data || []
                    );
                }

                renderHttpStatusPieChart(
                    data.metrics.http2xx || 0,
                    data.metrics.http3xx || 0,
                    data.metrics.http4xx || 0,
                    data.metrics.http5xx || 0
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
            console.error('Auto-refresh Nginx failed:', error);
            isErrorState = true;

            if (refreshIcon) refreshIcon.classList.remove('spin-icon');

            const liveText = document.getElementById('live-text');
            if (liveText) {
                liveText.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-exclamation-circle-fill me-1"></i>Update failed</span>';
            }
        }
    }

    // Auto Refresh every 30 seconds
    setInterval(fetchNginxData, 30000);
});
