@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 mb-1">Monitored servers</h1><p class="text-body-secondary mb-0">SSH password-authenticated monitoring targets.</p></div><a href="{{ route('servers.create') }}" class="btn btn-primary">Add server</a></div>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <div class="card shadow-sm border-0"><div class="table-responsive">

        @php
        function statusColor($cpu, $memory, $disk)
        {
            $cpu ??= 0;
            $memory ??= 0;
            $disk ??= 0;

            if ($cpu >= 90 || $memory >= 90 || $disk >= 85) {
                return ['danger', 'Critical'];
            }

            if ($cpu >= 70 || $memory >= 70 || $disk >= 75) {
                return ['warning', 'Warning'];
            }

            return ['success', 'Healthy'];
        }
        @endphp

        <table class="table table-hover mb-0">
            <thead>
                <tr>
                <th width="30%">Server</th>
                <th width="12%">CPU</th>
                <th width="12%">Memory</th>
                <th width="10%">Disk Usage</th>
                <th width="10%">Status</th>
                <th width="18%">Last Check</th>
                <th width="10%">Action</th>
                </tr>
            </thead>

            <tbody>

            @foreach($servers as $server)
            @php
            $cpu = $server->cpuMetrics->first();
            $memory = $server->memoryMetrics->first();
            $disk = $server->diskMetrics->first();
            [$color,$status] = statusColor(
                $cpu?->usage_percent,
                $memory?->usage_percent,
                $disk?->usage_percent
            );
            @endphp

            <tr>
            <td>
                <strong>
                    <a href="{{ route('servers.show',$server) }}">
                        {{ $server->name }}
                    </a>
                </strong>
                <br>
                <small class="text-muted">
                    {{ $server->hostname }}
                </small>

            </td>

            <td>
                @if($cpu)
                    <div class="progress" style="height:18px">
                        <div
                            class="progress-bar bg-success"
                            style="width: {{ $cpu->usage_percent }}%;">
                            {{ number_format($cpu->usage_percent,1) }}%
                        </div>
                    </div>
                @else
                    —
                @endif

            </td>

            <td>
                @if($memory)
                    <div class="progress" style="height:18px">
                        <div
                            class="progress-bar bg-info"
                            style="width: {{ $memory->usage_percent }}%;">
                            {{ number_format($memory->usage_percent,1) }}%
                        </div>
                    </div>
                @else
                    —
                @endif
            </td>

            <td>
                @if($disk)
                    <div class="progress" style="height:18px">
                        <div
                            class="progress-bar bg-warning"
                            style="width: {{ $disk->usage_percent }}%;">
                            {{ number_format($disk->usage_percent,1) }}%
                        </div>
                    </div>
                @else
                    —
                @endif

            </td>

            <td>

                @if(!$server->is_online)
                    <span class="badge bg-dark">
                        Offline
                    </span>

                @elseif($status == 'Critical')
                    <span class="badge bg-danger">
                        Critical
                    </span>

                @elseif($status == 'Warning')
                    <span class="badge bg-warning">
                        Warning
                    </span>

                @else
                    <span class="badge bg-success">
                        Healthy
                    </span>

                @endif

            </td>

            <td>
            {{ optional($cpu?->collected_at ?? $memory?->collected_at ?? $disk?->collected_at)->format('H:i:s') ?? '-' }}
            {{ $server->last_checked_at?->diffForHumans() }}
            </td>
            <td>
                <a
                    href="{{ route('servers.show',$server) }}"
                    class="btn btn-sm btn-primary">
                    Open
                </a>
            </td>
            </tr>
            @endforeach

            </tbody>

        </table>
    </div>
</div>
    <div class="mt-3">{{ $servers->links() }}</div>
@endsection
