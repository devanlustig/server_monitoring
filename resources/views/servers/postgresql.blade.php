@extends('layouts.app')

@section('content')



<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2>PostgreSQL Monitoring</h2>

        <div class="text-muted">

            {{ $server->name }}

        </div>

    </div>

    <a
        href="{{ route('servers.show',$server) }}"
        class="btn btn-secondary">

        Kembali

    </a>

</div>

<div class="row mb-4">

    <div class="col-md-3">

        <div class="card">

            <div class="card-body">

                <small>Connections</small>

                <h3>

                    {{ $summary->currentConnections }}

                    /

                    {{ $summary->maxConnections }}

                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card">

            <div class="card-body">

                <small>Usage</small>

                <h3>

                    {{ $summary->usagePercent }} %

                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card">

            <div class="card-body">

                <small>Active</small>

                <h3>

                    {{ $summary->activeConnections }}

                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card">

            <div class="card-body">

                <small>Idle</small>

                <h3>

                    {{ $summary->idleConnections }}

                </h3>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">
                    🛠 PostgreSQL Operations
                </h5>
                <small class="text-muted">
                    Execute PostgreSQL administrative actions
                </small>
            </div>
            <a
                href="{{ route('servers.postgresql',$server) }}"
                class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </a>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-warning border-warning">
            <strong>⚠ Warning</strong>
            <br>
            These operations affect live PostgreSQL connections.
            Use them only when necessary.
        </div>

        <div class="row g-4">

            <!-- <div class="col-lg-6">
                <div class="card border-danger h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="fs-2 me-3">
                                🔴
                            </div>
                            <div>
                                <h5 class="mb-1">
                                    Kill All Idle
                                </h5>
                                <small class="text-muted">
                                    Immediately terminate all idle client connections.
                                </small>
                            </div>
                        </div>
                        <form
                            method="POST"
                            action="{{ route('servers.postgresql.killIdle',$server) }}">
                            @csrf
                            <button
                                class="btn btn-danger w-100"
                                onclick="return confirm('Terminate ALL idle PostgreSQL connections?')">
                                Execute
                            </button>
                        </form>
                    </div>
                </div>
            </div> -->

            {{-- Kill Older Than --}}
            <div class="col-lg-3">
                <div class="card border-warning h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="fs-2 me-3">
                                🟡
                            </div>
                            <div>
                                <h5 class="mb-1">
                                    Kill Idle Older Than
                                </h5>
                                <small class="text-muted">
                                    Safer option for production servers.
                                </small>
                            </div>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('servers.postgresql.killIdleOlder',$server) }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-8">
                                    <select
                                        class="form-select"
                                        name="minutes">
                                        <option value="30">30 Minutes</option>
                                        <option value="60">1 Hour</option>
                                        <option value="360">6 Hours</option>
                                        <option value="720">12 Hours</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button
                                        class="btn btn-warning w-100"
                                        onclick="return confirm('Terminate idle PostgreSQL connections?')">
                                        Kill
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

           
                <div class="col-md-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">
                                Top Client
                            </div>
                            @if($topClient->isNotEmpty())
                                <h5 class="mb-0">
                                    {{ $topClient->keys()->first() }}
                                </h5>
                                <small>
                                    {{ $topClient->first() }} Connection(s)
                                </small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">
                                Top Application
                            </div>
                            @if($topApplication->isNotEmpty())
                                <h5 class="mb-0">
                                    {{ $topApplication->keys()->first() }}
                                </h5>
                                <small>
                                    {{ $topApplication->first() }} Connection(s)
                                </small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">
                                Oldest Connection
                            </div>
                            @if($oldestConnection)
                                <h5>
                                    {{ $oldestConnection->connectionAgeLabel() }}
                                </h5>
                                <small>
                                    {{ $oldestConnection->client }}
                                </small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">
                                Longest Idle
                            </div>
                            @if($longestIdle)
                                <h5>
                                    {{ $longestIdle->activityDurationLabel() }}
                                </h5>
                                <small>
                                    PID {{ $longestIdle->pid }}
                                </small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <form method="GET">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">

                                        Client

                                    </label>
                                    <select
                                        class="form-select"
                                        name="client">
                                        <option value="">
                                            All Client
                                        </option>
                                        @foreach($clients as $client)
                                            <option
                                                value="{{ $client }}"
                                                @selected(request('client')==$client)>
                                                {{ $client }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">
                                        Application
                                    </label>

                                    <select
                                        class="form-select"
                                        name="application">
                                        <option value="">
                                            All Application
                                        </option>
                                        @foreach($applications as $application)
                                            <option
                                                value="{{ $application }}"
                                                @selected(request('application')==$application)>
                                                {{ $application }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">
                                        Database
                                    </label>

                                    <select
                                        class="form-select"
                                        name="database">
                                        <option value="">
                                            All Database
                                        </option>

                                        @foreach($databases as $database)
                                            <option
                                                value="{{ $database }}"
                                                @selected(request('database')==$database)>
                                                {{ $database }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">
                                        State
                                    </label>
                                    <select
                                        class="form-select"
                                        name="state">
                                        <option value="">
                                            All
                                        </option>
                                        <option
                                            value="active"
                                            @selected(request('state')=='active')>
                                            ACTIVE
                                        </option>
                                        <option
                                            value="idle"
                                            @selected(request('state')=='idle')>
                                            IDLE
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-2 d-flex align-items-end gap-2">
                                    <button
                                        class="btn btn-primary w-100">
                                        Filter
                                    </button>
                                    <a
                                        href="{{ route('servers.postgresql',$server) }}"
                                        class="btn btn-outline-secondary">
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            
        </div>
    </div>
</div>
    

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <br>

<form
    method="POST"
    action="{{ route('servers.postgresql.killSelected',$server) }}">

@csrf

<div class="card">

    <div class="card-header">

        Active Connections

    </div>

    <div class="table-responsive">

        <table class="table table-hover table-sm mb-0">

            <thead>

            <tr>
                <th width="40"><input type="checkbox"id="check-all"></th>

                <th>PID</th>
                <th>Database</th>
                <th>User</th>
                <th>Application</th>
                <th>Client</th>
                <th>State</th>
                <th>Backend Start</th>
                <th>Connection Age</th>
                <th width="150">
                <a href="{{ request()->fullUrlWithQuery([
                        'sort' => 'activity_duration',
                        'direction' => request('direction') == 'asc' ? 'desc' : 'asc'
                    ]) }}"
                    class="text-decoration-none text-dark">
                    Activity Duration
                    @if(request('sort') == 'activity_duration')
                        {{ request('direction') == 'asc' ? '▲' : '▼' }}
                    @endif
                </a>
                </th>
                <th width="35%">Query</th>
                <th>Action</th>

            </tr>

            </thead>

            <tbody class="table-middle">

                @forelse($connections as $connection)
                <tr>
                    <td class="text-center">
                        @if($connection->canTerminate())
                            <input
                                type="checkbox"
                                name="pids[]"
                                value="{{ $connection->pid }}">
                        @endif
                    </td>
                    <td>{{ $connection->pid }}</td>
                    <td>{{ $connection->database }}</td>
                    <td>{{ $connection->user }}</td>
                    <td>{{ $connection->application }}</td>
                    <td>{{ $connection->client }}</td>
                    <td>
                    <span class="badge badge-lg bg-{{ $connection->stateBadgeColor() }}">
                        {{ strtoupper($connection->state) }}
                    </span>
                    </td>
                    <td class="small">
                        {{ $connection->backendStart }}
                    </td>
                    <td>
                    {{ $connection->connectionAgeLabel() }}
                    </td>
                    <td>
                        <span class="badge badge-lg bg-{{ $connection->activityBadgeColor() }}">
                        @if($connection->state=='active')
                        Running
                        @else
                        Idle
                        @endif
                        {{ $connection->activityDurationLabel() }}
                        </span>
                    </td>
                    <td style="max-width:300px">
                        <div class="query-code">
                            {{ Str::limit($connection->query,90) }}
                        </div>
                    </td>
                    <td class="text-center">

                        @if($connection->canTerminate())
                            <form
                                method="POST"
                                action="{{ route('servers.postgresql.terminate',[$server,$connection->pid]) }}">
                                @csrf
                                <button
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Terminate connection PID {{ $connection->pid }} ?')">
                                    Terminate
                                </button>
                            </form>
                        @else
                            <span class="badge bg-secondary">
                                Protected
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>

                    <td colspan="11" class="text-center py-4">
                        No PostgreSQL Connections
                    </td>
                </tr>

                @endforelse

                </tbody>

        </table>

    </div>

</div>

<div class="mt-3">

    <button
        class="btn btn-danger"
        onclick="return confirm('Terminate selected connection(s)?')">

        Terminate Selected

    </button>

</div>

</form>

@endsection


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const checkAll = document.getElementById('check-all');

    if (!checkAll) {
        return;
    }

    checkAll.addEventListener('change', function () {

        document.querySelectorAll('input[name="pids[]"]').forEach(function (checkbox) {

            checkbox.checked = checkAll.checked;

        });

    });

});


document.querySelectorAll('input[name="pids[]"]').forEach(function (checkbox) {

    checkbox.addEventListener('change', function () {

        const all = document.querySelectorAll('input[name="pids[]"]');

        const checked = document.querySelectorAll('input[name="pids[]"]:checked');

        checkAll.checked = all.length === checked.length;

    });

});

</script>

@endpush