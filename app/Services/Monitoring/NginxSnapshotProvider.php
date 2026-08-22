<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\NginxCollector;
use App\Services\Monitoring\DTO\MetricSnapshotData;
use App\Services\Monitoring\Support\MetricNames;
use Exception;

class NginxSnapshotProvider implements MetricSnapshotProvider
{
    public function __construct(
        private readonly NginxCollector $collector,
        private readonly NginxMonitoringService $service,
    ) {}

    public function getSnapshots(MonitoredServer $server): array
    {
        if ($server->web_server !== 'nginx') {
            return [];
        }

        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
        } catch (Exception $e) {
            logger()->error(
                "NginxSnapshotProvider failed for server {$server->name}: ".$e->getMessage()
            );

            return [];
        }

        if (! ($metrics['logFound'] ?? false)) {
            return [];
        }

        $snapshotAt = now();
        $snapshots = [];

        $definitions = [
            MetricNames::TOTAL_REQUESTS =>
                [$metrics['totalRequests'] ?? 0, 'requests'],

            MetricNames::REQUESTS_PER_MINUTE =>
                [$metrics['requestsPerMinute'] ?? 0, 'req/min'],

            MetricNames::REQUESTS_PER_HOUR =>
                [$metrics['requestsPerHour'] ?? 0, 'req/hour'],

            MetricNames::TOTAL_TRAFFIC =>
                [$metrics['totalTrafficBytes'] ?? 0, 'bytes'],

            MetricNames::ERROR_RATE =>
                [$metrics['errorRate'] ?? 0, '%'],

            MetricNames::SUCCESS_RATE =>
                [$metrics['successRate'] ?? 0, '%'],
        ];

        foreach ($definitions as $metricName => [$value, $unit]) {
            $snapshots[] = new MetricSnapshotData(
                category: 'nginx',
                metricName: $metricName,
                metricValue: (float) $value,
                metricUnit: $unit,
                snapshotAt: $snapshotAt,
            );
        }

        return $snapshots;
    }
}
