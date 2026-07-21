<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServerRequest;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Connections\ServerConnectionFactory;
use App\Services\Monitoring\ServerInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MonitoredServerController extends Controller
{
    public function __construct(
        private readonly ServerConnectionFactory $connections,
        private readonly ServerInformationService $serverInformation,
    ) {}

    public function index(): View
    {
        return view('servers.index', ['servers' => MonitoredServer::query()->latest()->paginate(15)]);
    }

    public function create(): View
    {
        return view('servers.create', ['server' => new MonitoredServer(['ssh_port' => 22, 'is_active' => true])]);
    }

    public function show(MonitoredServer $server): View
    {
        return view('servers.show', ['server' => $server, 'latestMetric' => $server->cpuMetrics()->latest('collected_at')->first()]);
    }

    public function store(ServerRequest $request): RedirectResponse
    {
        $server = new MonitoredServer($this->payload($request));
        $this->ensureConnection($server);
        $server->save();

        return to_route('servers.index')->with('status', 'Server saved after a successful SSH connection test.');
    }

    public function edit(MonitoredServer $server): View
    {
        return view('servers.edit', compact('server'));
    }

    public function update(ServerRequest $request, MonitoredServer $server): RedirectResponse
    {
        $server->fill($this->payload($request, $server));
        $this->ensureConnection($server);
        $server->save();

        return to_route('servers.index')->with('status', 'Server updated after a successful SSH connection test.');
    }

    public function destroy(MonitoredServer $server): RedirectResponse
    {
        $server->delete();

        return to_route('servers.index')->with('status', 'Server removed.');
    }

    public function test(ServerRequest $request): JsonResponse
    {
        $server = new MonitoredServer($this->payload($request));
        $result = $this->connections->for($server)->test($server);

        return response()->json(['connected' => $result->successful, 'message' => $result->message], $result->successful ? 200 : 422);
    }

    private function ensureConnection(MonitoredServer $server): void
    {
        $result = $this->connections->for($server)->test($server);

        if (! $result->successful) {
            throw ValidationException::withMessages(['ssh_username' => $result->message]);
        }

        try {
            $server->fill($this->serverInformation->collect($server));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['hostname' => 'SSH connection succeeded, but system information could not be collected.']);
        }
    }

    private function payload(ServerRequest $request, ?MonitoredServer $existing = null): array
    {
        $data = $request->safe()->only(['name', 'hostname', 'ssh_port', 'ssh_username', 'ssh_password', 'environment', 'description']);
        $data['authentication_method'] = 'ssh_password';
        $data['is_active'] = $request->boolean('is_active');

        if ($existing !== null && blank($data['ssh_password'] ?? null)) {
            unset($data['ssh_password']);
        }

        return $data;
    }
}
