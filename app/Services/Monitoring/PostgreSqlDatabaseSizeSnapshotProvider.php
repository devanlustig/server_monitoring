<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\PostgreSqlDatabaseSizeCollector;
use Exception;
use Throwable;

class PostgreSqlDatabaseSizeSnapshotProvider implements MetricSnapshotProvider
{
    public function __construct(
        private readonly PostgreSqlDatabaseSizeCollector $collector
    ) {}

    public function getSnapshots(MonitoredServer $server): array
    {
        try {
            $this->collector->collect($server);
        } catch (Throwable $e) {
            logger()->warning("PostgreSqlDatabaseSizeSnapshotProvider failed for server {$server->name}: " . $e->getMessage());
        }

        return [];
    }
}
