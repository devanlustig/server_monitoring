@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">{{ $server->name }}</h1>
        <p class="text-muted mb-0">
            <code>{{ $server->hostname }}:{{ $server->ssh_port }}</code>
        </p>
    </div>

    <a href="{{ route('servers.edit', $server) }}" class="btn btn-outline-primary">
        Edit Server
    </a>

    <a href="{{ route('servers.postgresql',$server) }}"
    class="btn btn-success"> PostgreSQL </a>

</div>



<div class="row">

    {{-- Server Information --}}
    <div class="col-lg-6 mb-4">

        <div class="card shadow-sm h-100">

            <div class="card-header">
                <strong>System Information</strong>
            </div>

            <div class="card-body">

                <table class="table table-sm mb-0">

                    <tr>
                        <th width="35%">Hostname</th>
                        <td>{{ $server->system_hostname }}</td>
                    </tr>

                    <tr>
                        <th>Operating System</th>
                        <td>{{ $server->operating_system }}</td>
                    </tr>

                    <tr>
                        <th>Kernel</th>
                        <td>{{ $server->kernel_version }}</td>
                    </tr>

                    <tr>
                        <th>CPU Model</th>
                        <td>{{ $server->cpu_model }}</td>
                    </tr>

                    <tr>
                        <th>CPU Core</th>
                        <td>{{ $server->cpu_core_count }}</td>
                    </tr>

                    <tr>
                        <th>Total RAM</th>
                        <td>{{ number_format($server->total_ram_bytes / 1024 / 1024 /1024,2) }} GB</td>
                    </tr>

                    <tr>
                        <th>Total Disk</th>
                        <td>{{ number_format($server->total_disk_bytes / 1024 /1024 /1024,2) }} GB</td>
                    </tr>

                    <tr>
                        <th>Last SSH</th>
                        <td>

                            {{ $server->last_successful_connection_at?->format('d M Y H:i:s') }}

                        </td>
                    </tr>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                    @if($server->is_online)
                    <span class="badge bg-success">Online</span>
                    @else
                    <span class="badge bg-dark">Offline</span>
                    @endif
                    </dd>

                    <dt class="col-sm-4">Last Checked</dt>
                    <dd class="col-sm-8">
                    {{ $server->last_checked_at?->format('Y-m-d H:i:s') ?? '-' }}
                    </dd>

                    <dt class="col-sm-4">Last Error</dt>
                    <dd class="col-sm-8">
                    {{ $server->last_error ?? '-' }}
                    </dd>

                </table>

            </div>

        </div>

    </div>



    {{-- CPU Card --}}
    <div class="col-lg-6 mb-4">

        <div class="card shadow-sm h-100">

            <div class="card-header">

                <strong>CPU Monitoring</strong>

            </div>

            <div class="card-body">

                @php

                    $usage = $latestMetric?->usage_percent ?? 0;

                    if($usage < 60){

                        $color = "success";
                        $status = "Healthy";

                    }elseif($usage < 85){

                        $color = "warning";
                        $status = "Warning";

                    }else{

                        $color = "danger";
                        $status = "Critical";

                    }

                @endphp


                <div class="text-center mb-4">

                    <h1 class="display-3 fw-bold">

                        {{ $latestMetric?->usage_percent !== null ? number_format($latestMetric->usage_percent,2).'%' : '--' }}

                    </h1>

                    <span class="badge bg-{{ $color }}">

                        {{ $status }}

                    </span>

                </div>


                <div class="progress mb-4" style="height:25px;">

                    <div

                        class="progress-bar bg-{{ $color }}"

                        role="progressbar"

                        style="width: {{ $usage }}%;">

                        {{ number_format($usage,2) }}%

                    </div>

                </div>


                <table class="table table-bordered">

                    <tr>

                        <th width="40%">Load 1 Minute</th>

                        <td>{{ $latestMetric->load_1 ?? '-' }}</td>

                    </tr>

                    <tr>

                        <th>Load 5 Minutes</th>

                        <td>{{ $latestMetric->load_5 ?? '-' }}</td>

                    </tr>

                    <tr>

                        <th>Load 15 Minutes</th>

                        <td>{{ $latestMetric->load_15 ?? '-' }}</td>

                    </tr>

                    <tr>

                        <th>Last Update</th>

                        <td>

                            {{ $latestMetric?->collected_at?->format('d M Y H:i:s') }}

                        </td>

                    </tr>

                </table>

            </div>

        </div>

    </div>

    <div class="col-lg-6 mb-4">
    <div class="card shadow-sm border-0 mt-3">

             <div class="card-header">

                <strong> Latest Disk Usage </strong>

            </div>

            <div class="card-body">

                @if($latestDisk)

                <p>
                Disk Usage
                <strong>

                {{ number_format($latestDisk->usage_percent,2) }}%

                </strong>

                </p>

                <p>

                Used

                {{ number_format($latestDisk->used/1024/1024/1024,2) }}

                GB

                </p>

                <p>

                Available

                {{ number_format($latestDisk->available/1024/1024/1024,2) }}

                GB

                </p>

                <p>

                Total

                {{ number_format($latestDisk->total/1024/1024/1024,2) }}

                GB

                </p>

                @else

                No disk sample.

                @endif

            </div>

        </div>
        </div>

</div>

{{-- History CPU --}}
<div class="card shadow-sm">

    <div class="card-header">

        <strong>CPU Usage History</strong>

    </div>

    <div class="card-body">

        <canvas id="cpuChart" height="90"></canvas>

    </div>

</div>

@endsection