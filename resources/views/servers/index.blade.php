@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 mb-1">Monitored servers</h1><p class="text-body-secondary mb-0">SSH password-authenticated monitoring targets.</p></div><a href="{{ route('servers.create') }}" class="btn btn-primary">Add server</a></div>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Host</th><th>SSH user</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse ($servers as $server)
            <tr><td><a href="{{ route('servers.show', $server) }}">{{ $server->name }}</a></td><td><code>{{ $server->hostname }}:{{ $server->ssh_port }}</code></td><td>{{ $server->ssh_username }}</td><td><span class="badge text-bg-{{ $server->is_active ? 'success' : 'secondary' }}">{{ $server->is_active ? 'Active' : 'Paused' }}</span></td><td class="text-end"><a href="{{ route('servers.edit', $server) }}" class="btn btn-sm btn-outline-primary">Edit</a><form class="d-inline" method="POST" action="{{ route('servers.destroy', $server) }}">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this server?')">Delete</button></form></td></tr>
        @empty
            <tr><td colspan="5" class="p-4 text-body-secondary">No servers configured.</td></tr>
        @endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $servers->links() }}</div>
@endsection
