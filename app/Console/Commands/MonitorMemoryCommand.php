<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\MemoryCollector;
use App\Services\Monitoring\Recorders\MemoryMetricRecorder;
use App\Services\Monitoring\ServerStatusService;

class MonitorMemoryCommand extends BaseMonitorCommand
{
    protected $signature = 'monitor:memory';

    protected $description = 'Collect Memory metrics';

    public function __construct(
        private readonly MemoryCollector $collector,
        private readonly MemoryMetricRecorder $recorder,
        ServerStatusService $status,
    ) {
        parent::__construct($status);
    }

    protected function monitorName(): string
    {
        return 'Memory';
    }

    protected function collectMetric(MonitoredServer $server): void
    {
        $metric = $this->collector->collect($server);

        $this->recorder->record($server, $metric);
    }
}