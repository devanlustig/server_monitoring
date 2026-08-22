@extends('layouts.app')

@section('content')

<!-- Bootstrap Icons & Chart.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .page-header {
        background: linear-gradient(135deg, #0d6efd, #00c9a7);
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
        <h2 class="mb-1 fw-bold text-white"><i class="bi bi-server me-2"></i>Nginx Monitoring</h2>
        <div class="text-white-50 opacity-75 fs-5 d-flex align-items-center gap-3 flex-wrap">
            <span>{{ $server->name }} (<code>{{ $server->hostname }}</code>)</span>
            <span class="d-inline-flex align-items-center gap-1 bg-white bg-opacity-10 px-2 py-1 rounded small text-white" id="live-indicator">
                <i class="bi bi-arrow-repeat" id="refresh-icon"></i>
                <span id="live-text"><span class="text-success-light fw-bold" style="color: #52c41a;">LIVE</span> • <span id="time-ago-text">Updated just now</span></span>
            </span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('servers.nginx', $server) }}" class="btn btn-light btn-sm fw-semibold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </a>
        <a href="{{ route('servers.show', $server) }}" class="btn btn-outline-light btn-sm fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Main Partial Content Container -->
<div id="nginx-main-content">
    @include('servers.partials.nginx-content')
</div>

@endsection

@push('scripts')
<script>
window.nginxConfig = {
    refreshUrl: "{{ route('servers.nginx.refresh', $server) }}",
    historyUrl: "{{ route('servers.nginx.history', $server) }}",

    initialTimeline: {
        labels: @json($metrics['requestTimeline']['labels'] ?? []),
        data: @json($metrics['requestTimeline']['data'] ?? [])
    },

    initialHistory: {
        labels: @json($history['chart']->labels),
        values: @json($history['chart']->values)
    },

    httpStatus: {
        success: {{ $metrics['http2xx'] }},
        redirect: {{ $metrics['http3xx'] }},
        client: {{ $metrics['http4xx'] }},
        server: {{ $metrics['http5xx'] }}
    }
};
</script>

@vite('resources/js/monitoring/nginx.js')
@endpush