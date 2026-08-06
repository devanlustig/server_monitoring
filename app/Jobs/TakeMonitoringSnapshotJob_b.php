<?php

namespace App\Jobs;

use App\Models\MonitoredServer;
use App\Services\Monitoring\ApacheSnapshotProvider;
use App\Services\Monitoring\MetricHistoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TakeMonitoringSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        MetricHistoryService $historyService
    ): void {
        $servers = MonitoredServer::all();
        $providers = collect(config('monitoring.providers', []))->map(fn ($provider) => app($provider));

        foreach ($servers as $server) {
            $allSnapshots = [];
            foreach ($providers as $provider) {
                try {
                    $allSnapshots = array_merge(
                        $allSnapshots,
                        $provider->getSnapshots($server)
                    );

                } catch (\Throwable $e) {
                    logger()->error(
                        sprintf(
                            'Snapshot failed [%s] on server %s : %s',
                            class_basename($provider),
                            $server->name,
                            $e->getMessage()
                        )
                    );
                }
            }

            if (! empty($allSnapshots)) {
                $count = $historyService->store(
                    $server,
                    $allSnapshots
                );
                logger()->info('Snapshot stored', [
                    'server' => $server->name,
                    'metrics' => $count,
                ]);
            }
        }
    }
}
