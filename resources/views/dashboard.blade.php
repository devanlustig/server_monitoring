@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1">Monitoring dashboard</h1>
            <p class="text-body-secondary mb-0">Your server-monitoring modules will appear here.</p>
        </div>
        <span class="badge text-bg-primary fs-6">{{ $serverCount }} servers</span>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Recently registered servers</div>
        <div class="card-body p-0">
            @if ($recentServers->isEmpty())
                <p class="text-body-secondary mb-0 p-4">No servers have been registered yet.</p>
            @else
                <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Endpoint</th><th>Status</th></tr></thead><tbody>
                    @foreach ($recentServers as $server)
                        <tr><td>{{ $server->name }}</td><td><code>{{ $server->hostname }}</code></td><td><span class="badge text-bg-{{ $server->is_active ? 'success' : 'secondary' }}">{{ $server->is_active ? 'Active' : 'Paused' }}</span></td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </div>
    </div>
@endsection
