<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\DiskCollector;
use App\Services\Monitoring\Recorders\DiskMetricRecorder;
use App\Services\Monitoring\ServerStatusService;

class MonitorDiskCommand extends BaseMonitorCommand
{
    protected $signature = 'monitor:disk';

    protected $description = 'Collect Disk metrics';

    public function __construct(
        private readonly DiskCollector $collector,
        private readonly DiskMetricRecorder $recorder,
        ServerStatusService $status,
    ) {
        parent::__construct($status);
    }

    protected function monitorName(): string
    {
        return 'Disk';
    }

    protected function collectMetric(MonitoredServer $server): void
    {
        $metric = $this->collector->collect($server);

        $this->recorder->record($server, $metric);
    }
}