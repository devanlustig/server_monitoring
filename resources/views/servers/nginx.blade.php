@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                Nginx Monitoring
            </h4>
            <div class="text-muted">
                {{ $server->name }}
                · {{ $server->hostname }}
            </div>
        </div>
        <a href="{{ route('servers.show', $server) }}"
           class="btn btn-outline-secondary">
            ← Server Detail
        </a>
    </div>

    {{-- Health --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="text-muted small">
                        Health Score
                    </div>
                    <div class="display-6 fw-bold">
                        {{ $metrics['healthScore'] }}%
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">
                        Success Rate
                    </div>
                    <div class="fs-3 fw-bold text-success">
                        {{ $metrics['successRate'] }}%
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">
                        Error Rate
                    </div>
                    <div class="fs-3 fw-bold text-danger">
                        {{ $metrics['errorRate'] }}%
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">
                        Traffic
                    </div>
                    <div class="fs-3 fw-bold">
                        {{ $traffic }}
                    </div>
                </div>
            </div>
        </div>
    </div>


    {{-- Request Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Total Requests
                    </div>
                    <div class="fs-3 fw-bold">
                        {{ number_format($metrics['totalRequests']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Requests / Minute
                    </div>
                    <div class="fs-3 fw-bold">
                        {{ $metrics['requestsPerMinute'] }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Requests / Hour
                    </div>
                    <div class="fs-3 fw-bold">
                        {{ number_format($metrics['requestsPerHour']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">
                        Traffic
                    </div>
                    <div class="fs-3 fw-bold">
                        {{ $traffic }}
                    </div>
                </div>
            </div>
        </div>

    </div>


    {{-- HTTP Status --}}
    <div class="card">

        <div class="card-header">
            <strong>HTTP Status</strong>
        </div>

        <div class="card-body">

            <div class="row text-center">

                <div class="col">
                    <div class="text-muted small">
                        2xx
                    </div>
                    <div class="fs-4 fw-bold text-success">
                        {{ number_format($metrics['http2xx']) }}
                    </div>
                </div>

                <div class="col">
                    <div class="text-muted small">
                        3xx
                    </div>

                    <div class="fs-4 fw-bold">
                        {{ number_format($metrics['http3xx']) }}
                    </div>
                </div>

                <div class="col">
                    <div class="text-muted small">
                        4xx
                    </div>

                    <div class="fs-4 fw-bold text-warning">
                        {{ number_format($metrics['http4xx']) }}
                    </div>
                </div>

                <div class="col">
                    <div class="text-muted small">
                        5xx
                    </div>

                    <div class="fs-4 fw-bold text-danger">
                        {{ number_format($metrics['http5xx']) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection