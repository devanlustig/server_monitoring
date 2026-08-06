@extends('layouts.app')

@section('content')

<!-- Bootstrap Icons & Chart.js -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .page-header {
        background: linear-gradient(135deg, #0d6efd, #8e1592);
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
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }
    .info-table th {
        color: #6c757d;
        font-weight: 600;
        font-size: 0.875rem;
        width: 40%;
    }
    .info-table td {
        font-weight: 500;
        font-size: 0.9rem;
    }
    .partial-contents {
        display: contents;
    }

    /* Auto Refresh Indicator Animations */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin-icon {
        display: inline-block;
        animation: spin 0.8s linear infinite;
    }

    @keyframes highlightGreen {
        0% { background-color: rgba(25, 135, 84, 0.4); color: #fff; padding: 2px 6px; border-radius: 4px; }
        100% { background-color: transparent; }
    }
    .highlight-fade {
        animation: highlightGreen 0.8s ease-out;
    }

    @keyframes pulseBadge {
        0% { transform: scale(1); }
        50% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }
    .pulse-once {
        animation: pulseBadge 0.5s ease-in-out;
        display: inline-block;
    }
</style>

<div id="partial-header">
    @include('servers.partials.header')
</div>

<div class="row g-4 mb-4">
    <div id="partial-system-information" class="partial-contents">
        @include('servers.partials.system-information')
    </div>
    <div id="partial-cpu-detail" class="partial-contents">
        @include('servers.partials.cpu-detail')
    </div>
</div>

<div class="row g-4 mb-4">
    <div id="partial-memory-detail" class="partial-contents">
        @include('servers.partials.memory-detail')
    </div>
    <div id="partial-disk-detail" class="partial-contents">
        @include('servers.partials.disk-detail')
    </div>
</div>

<div id="partial-cpu-chart">
    @include('servers.partials.cpu-chart')
</div>

@endsection

@include('servers.partials.cpu-chart-script')