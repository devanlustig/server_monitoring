let TimelineChartInstance = null;
let httpStatusPieChartInstance = null;
let historyChart = null;
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


function renderHistoryChart(labels, values){
    const canvas = document.getElementById('historyResponseChart');
    if(!canvas) return;
    if(historyChart){
        historyChart.destroy();
    }
    historyChart = new Chart(canvas,{
        type:'line',
        data:{
            labels:labels,
            datasets:[{
                label:'Average Response Time',
                data:values,
                borderColor:'#198754',
                backgroundColor:'rgba(25,135,84,.15)',
                fill:true,
                tension:.35
            }]
        },

        options:{
            responsive:true,
            maintainAspectRatio:false,
            plugins:{
                legend:{
                    display:false
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

document.addEventListener('DOMContentLoaded', function () {
    const historyUrl=window.apacheConfig.historyUrl;
    const initialLabels=window.apacheConfig.initialTimeline.labels;
    const initialData=window.apacheConfig.initialTimeline.data;
    renderApacheTimelineChart(initialLabels, initialData);
    renderHistoryChart(
        window.apacheConfig.initialHistory.labels,
        window.apacheConfig.initialHistory.values
    );

    renderHttpStatusPieChart(

        window.apacheConfig.httpStatus.success,
        window.apacheConfig.httpStatus.redirect,
        window.apacheConfig.httpStatus.client,
        window.apacheConfig.httpStatus.server

    );

    bindHistoryEvents();

    const refreshUrl=window.apacheConfig.refreshUrl;
    let lastSuccessTimestamp = Date.now();
    let isErrorState = false;

    async function loadHistory(period,metric){

        const response=await fetch(historyUrl+'?period='+period+'&metric='+metric,{
                headers:{
                    'Accept':'application/json'
                }
            }
        );

        const json = await response.json();
        document.getElementById('summaryCurrent').innerHTML =
            json.summary.current.toFixed(1) + ' ms';
        document.getElementById('summaryAverage').innerHTML =
            json.summary.average.toFixed(1) + ' ms';
        document.getElementById('summaryMaximum').innerHTML =
            json.summary.maximum.toFixed(1) + ' ms';
        document.getElementById('summaryMinimum').innerHTML =
            json.summary.minimum.toFixed(1) + ' ms';
        renderHistoryChart(
            json.chart.labels,
            json.chart.values
        );

    }

    function bindHistoryEvents(){

        const period=document.getElementById('historyPeriod');
        const metric=document.getElementById('historyMetric');

        console.log(period);
        console.log(metric);

        if(period){
            period.onchange=function(){
                console.log('Period',this.value);
                loadHistory(this.value,metric.value);
            };
        }

        if(metric){
            metric.onchange=function(){
                console.log('Metric',this.value);
                loadHistory(period.value,this.value);
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
                bindHistoryEvents();
            }

            if (data.metrics) {
                if (data.metrics.requestTimeline) {
                    renderApacheTimelineChart(
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