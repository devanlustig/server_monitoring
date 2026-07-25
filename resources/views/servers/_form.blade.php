@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card shadow-sm border-0"><div class="card-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $server->name) }}" required></div>
    <div class="col-md-6"><label class="form-label">Hostname or IP</label><input class="form-control" name="hostname" value="{{ old('hostname', $server->hostname) }}" required></div>
    <div class="col-md-4"><label class="form-label">SSH port</label><input class="form-control" type="number" name="ssh_port" value="{{ old('ssh_port', $server->ssh_port ?? 22) }}" min="1" max="65535" required></div>
    <div class="col-md-4"><label class="form-label">PostgreSQL Port</label><input class="form-control"type="number"name="postgres_port"
        value="{{ old('postgres_port', $server->postgres_port ?? 5432) }}"min="1"max="65535"required></div>
    <div class="col-md-4"><label class="form-label">SSH username</label><input class="form-control" name="ssh_username" value="{{ old('ssh_username', $server->ssh_username) }}" required></div>
    <div class="col-md-4"><label class="form-label">SSH password</label><input class="form-control" type="password" name="ssh_password" {{ $server->exists ? '' : 'required' }} autocomplete="new-password"><div class="form-text">{{ $server->exists ? 'Leave blank to keep the existing password.' : 'Encrypted at rest by Laravel.' }}</div></div>
    <div class="col-md-6"><label class="form-label">Environment</label><input class="form-control" name="environment" value="{{ old('environment', $server->environment) }}"></div>
    <div class="col-md-6"><label class="form-label">Description</label><input class="form-control" name="description" value="{{ old('description', $server->description) }}"></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $server->is_active))><label class="form-check-label" for="active">Actively monitor this server</label></div></div>
</div></div><div class="card-footer bg-white d-flex gap-2"><button class="btn btn-outline-secondary" type="button" id="test-connection">Test connection</button><button class="btn btn-primary" type="submit">{{ $submitLabel }}</button><a class="btn btn-link" href="{{ route('servers.index') }}">Cancel</a><span id="connection-result" class="align-self-center"></span></div></div>
<script>
document.getElementById('test-connection').addEventListener('click', async () => {
    const form = document.querySelector('form'); const result = document.getElementById('connection-result'); result.textContent = 'Testing…'; result.className = 'align-self-center text-body-secondary';
    const response = await fetch('{{ route('servers.test-connection') }}', {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'}, body: new FormData(form)});
    const payload = await response.json(); result.textContent = payload.message || 'Connection test failed.'; result.className = 'align-self-center ' + (response.ok ? 'text-success' : 'text-danger');
});
</script>
