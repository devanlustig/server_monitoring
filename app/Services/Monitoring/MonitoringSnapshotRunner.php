<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\RemoteCommandService;

class MonitoringSnapshotRunner
{
    public function __construct(
        private readonly MetricHistoryService $historyService,
        private readonly RemoteCommandService $commands,
    ) {}

    public function run(): void
    {
        $servers = MonitoredServer::where('is_active', true)->get();
        $providers = collect(
            config('monitoring.providers', [])
        )->map(
            fn ($provider) => app($provider)
        );
        foreach ($servers as $server) {
            $snapshots = [];
            try {
                foreach ($providers as $provider) {
                    try {
                        $snapshots = array_merge(
                            $snapshots,
                            $provider->getSnapshots($server)
                        );
                    } catch (\Throwable $e) {
                        logger()->error(sprintf(
                            'Snapshot failed [%s] on %s : %s',
                            class_basename($provider),
                            $server->name,
                            $e->getMessage()
                        ));
                    }
                }

                if (! empty($snapshots)) {
                    $count = $this->historyService->store(
                        $server,
                        $snapshots
                    );
                }
            } finally {
                try {
                    $this->commands->disconnect($server);
                } catch (\Throwable) {}
            }
        }
    }
}