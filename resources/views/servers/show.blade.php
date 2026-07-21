@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 mb-1">{{ $server->name }}</h1><p class="text-body-secondary mb-0"><code>{{ $server->hostname }}:{{ $server->ssh_port }}</code></p></div><a class="btn btn-outline-primary" href="{{ route('servers.edit', $server) }}">Edit server</a></div>
    <div class="row g-3">
        <div class="col-lg-7"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white fw-semibold">System information</div><dl class="row mb-0 p-3">
            <dt class="col-sm-4">Hostname</dt><dd class="col-sm-8">{{ $server->system_hostname ?? 'Not collected' }}</dd>
            <dt class="col-sm-4">Operating system</dt><dd class="col-sm-8">{{ $server->operating_system ?? 'Not collected' }}</dd>
            <dt class="col-sm-4">Kernel version</dt><dd class="col-sm-8">{{ $server->kernel_version ?? 'Not collected' }}</dd>
            <dt class="col-sm-4">CPU model</dt><dd class="col-sm-8">{{ $server->cpu_model ?? 'Not collected' }}</dd>
            <dt class="col-sm-4">CPU cores</dt><dd class="col-sm-8">{{ $server->cpu_core_count ?? 'Not collected' }}</dd>
            <dt class="col-sm-4">Total RAM</dt><dd class="col-sm-8">{{ $server->total_ram_bytes ? number_format($server->total_ram_bytes / 1024 / 1024 / 1024, 2).' GB' : 'Not collected' }}</dd>
            <dt class="col-sm-4">Total disk</dt><dd class="col-sm-8">{{ $server->total_disk_bytes ? number_format($server->total_disk_bytes / 1024 / 1024 / 1024, 2).' GB' : 'Not collected' }}</dd>
            <dt class="col-sm-4">Last successful SSH connection</dt><dd class="col-sm-8">{{ $server->last_successful_connection_at?->format('Y-m-d H:i:s T') ?? 'Never' }}</dd>
        </dl></div></div>
        <div class="col-lg-5"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white fw-semibold">Latest CPU sample</div><div class="card-body">
            @if ($latestMetric)<p class="mb-2"><strong>{{ $latestMetric->usage_percent ?? '—' }}%</strong> CPU usage</p><p class="mb-2">Load: {{ $latestMetric->load_1 ?? '—' }} / {{ $latestMetric->load_5 ?? '—' }} / {{ $latestMetric->load_15 ?? '—' }}</p><p class="mb-0 text-body-secondary">{{ $latestMetric->collected_at->format('Y-m-d H:i:s T') }}</p>@else <p class="text-body-secondary mb-0">No CPU samples collected yet.</p>@endif
        </div></div></div>
    </div>
@endsection
