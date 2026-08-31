<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\PostgreSqlCollector;
use App\Services\Monitoring\DTO\MetricSnapshotData;
use Exception;

class PostgreSqlSnapshotProvider implements MetricSnapshotProvider
{
    public function __construct(
        private readonly PostgreSqlCollector $collector
    ) {}

    public function getSnapshots(MonitoredServer $server): array
    {
        if (empty($server->postgres_port)) {
            return [];
        }

        try {
            $summary = $this->collector->collect($server);
        } catch (Exception $e) {
            logger()->warning("PostgreSqlSnapshotProvider failed for server {$server->name}: " . $e->getMessage());
            return [];
        }

        $snapshotAt = now();
        $snapshots = [];

        $definitions = [
            'active_connections' => [$summary->activeConnections, 'connections'],
            'idle_connections' => [$summary->idleConnections, 'connections'],
            'active_queries' => [$summary->activeConnections, 'queries'], // active connection usually runs an active query
            'current_connections' => [$summary->currentConnections, 'connections'],
        ];

        foreach ($definitions as $metricName => [$value, $unit]) {
            $snapshots[] = new MetricSnapshotData(
                category: 'postgresql',
                metricName: $metricName,
                metricValue: (float) $value,
                metricUnit: $unit,
                snapshotAt: $snapshotAt
            );
        }

        return $snapshots;
    }
}
