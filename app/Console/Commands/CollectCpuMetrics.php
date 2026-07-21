<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\Monitoring\CpuMonitorService;
use Illuminate\Console\Command;
use Throwable;

class CollectCpuMetrics extends Command
{
    protected $signature = 'monitor:cpu';

    protected $description = 'Collect CPU samples from all active SSH servers';

    public function handle(CpuMonitorService $cpuMonitor): int
    {
        $collected = 0;

        MonitoredServer::query()->where('is_active', true)->eachById(function (MonitoredServer $server) use ($cpuMonitor, &$collected): void {
            try {
                $cpuMonitor->collect($server);
                $collected++;
            } catch (Throwable $exception) {
                $this->warn("{$server->name}: {$exception->getMessage()}");
            }
        });

        $this->info("CPU samples saved for {$collected} active server(s).");

        return self::SUCCESS;
    }
}
