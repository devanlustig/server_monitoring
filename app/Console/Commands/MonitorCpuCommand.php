<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\CpuCollector;
use App\Services\Monitoring\Recorders\CpuMetricRecorder;
use App\Services\Monitoring\ServerStatusService;

class MonitorCpuCommand extends BaseMonitorCommand
{
    protected $signature = 'monitor:cpu';

    protected $description = 'Collect CPU metrics';

    public function __construct(
    private readonly CpuCollector $collector,
    private readonly CpuMetricRecorder $recorder,
    ServerStatusService $status,
    ) {
        parent::__construct($status);
    }

    protected function monitorName(): string
    {
        return 'CPU';
    }

    protected function collectMetric(MonitoredServer $server): void
    {
        $metric = $this->collector->collect($server);

        $this->recorder->record($server, $metric);
    }
}