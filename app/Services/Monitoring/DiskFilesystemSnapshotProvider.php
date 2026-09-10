<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\DiskFilesystemCollector;
use Exception;

class DiskFilesystemSnapshotProvider implements MetricSnapshotProvider
{
    public function __construct(
        private readonly DiskFilesystemCollector $collector
    ) {}

    public function getSnapshots(MonitoredServer $server): array
    {
        try {
            $this->collector->collect($server);
        } catch (Exception $e) {
            logger()->warning("DiskFilesystemSnapshotProvider failed for server {$server->name}: " . $e->getMessage());
        }

        return [];
    }
}
