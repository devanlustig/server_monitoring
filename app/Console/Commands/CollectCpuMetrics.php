<?php

namespace App\Console\Commands;

use App\Services\Monitoring\CpuMonitorService;
use Illuminate\Console\Command;

class CollectCpuMetrics extends Command
{
    protected $signature = 'monitor:cpu';

    protected $description = 'Collect a CPU sample for the local monitoring host';

    public function handle(CpuMonitorService $cpuMonitor): int
    {
        $metric = $cpuMonitor->collect();

        $this->info(sprintf(
            'CPU sample saved for %s: %s%%.',
            $metric->hostname,
            $metric->usage_percent ?? 'unavailable',
        ));

        return self::SUCCESS;
    }
}
