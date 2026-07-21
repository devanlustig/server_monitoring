@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h2 mb-1">CPU monitoring</h1><p class="text-body-secondary mb-0">Local monitoring-host samples, collected every minute.</p></div>
        <span class="badge text-bg-secondary">{{ $latestMetric?->hostname ?? 'No samples' }}</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-body-secondary small">CPU usage</div><div class="display-6">{{ $latestMetric?->usage_percent !== null ? $latestMetric->usage_percent.'%' : '—' }}</div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-body-secondary small">1-minute load</div><div class="display-6">{{ $latestMetric?->load_1 ?? '—' }}</div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-body-secondary small">Logical cores</div><div class="display-6">{{ $latestMetric?->core_count ?? '—' }}</div></div></div></div>
    </div>

    <div class="card shadow-sm border-0"><div class="card-header bg-white fw-semibold">Recent samples</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Collected</th><th>Usage</th><th>Load (1 / 5 / 15)</th><th>Cores</th></tr></thead><tbody>
        @forelse ($metrics as $metric)
            <tr><td>{{ $metric->collected_at->format('Y-m-d H:i:s T') }}</td><td>{{ $metric->usage_percent !== null ? $metric->usage_percent.'%' : '—' }}</td><td>{{ $metric->load_1 ?? '—' }} / {{ $metric->load_5 ?? '—' }} / {{ $metric->load_15 ?? '—' }}</td><td>{{ $metric->core_count ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-body-secondary p-4">No CPU samples yet. Run <code>php artisan monitor:cpu</code> or start the scheduler.</td></tr>
        @endforelse
    </tbody></table></div></div>
@endsection
