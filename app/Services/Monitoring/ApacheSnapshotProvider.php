<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\ApacheCollector;
use App\Services\Monitoring\DTO\MetricSnapshotData;
use App\Services\Monitoring\Support\MetricNames;
use Exception;

class ApacheSnapshotProvider implements MetricSnapshotProvider
{
    public function __construct(
        private readonly ApacheCollector $collector,
        private readonly ApacheMonitoringService $service,
    ) {}

    public function getSnapshots(MonitoredServer $server): array
    {
        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
        } catch (Exception $e) {
            logger()->error(
                "ApacheSnapshotProvider failed for server {$server->name}: ".$e->getMessage()
            );

            return [];
        }

        if (! $metrics->logFound) {
            return [];
        }

        $snapshotAt = now();
        $snapshots = [];

        $definitions = [

            MetricNames::TOTAL_REQUESTS =>
                [$metrics->totalRequests, 'requests'],

            MetricNames::REQUESTS_PER_MINUTE =>
                [$metrics->requestsPerMinute, 'req/min'],

            MetricNames::REQUESTS_PER_HOUR =>
                [$metrics->requestsPerHour, 'req/hour'],

            MetricNames::TOTAL_TRAFFIC_BYTES =>
                [$metrics->totalTrafficBytes, 'bytes'],

            MetricNames::HTTP_2XX =>
                [$metrics->http2xx, 'requests'],

            MetricNames::HTTP_3XX =>
                [$metrics->http3xx, 'requests'],

            MetricNames::HTTP_4XX =>
                [$metrics->http4xx, 'requests'],

            MetricNames::HTTP_5XX =>
                [$metrics->http5xx, 'requests'],

            MetricNames::ERROR_RATE =>
                [$metrics->errorRate, '%'],

            MetricNames::SUCCESS_RATE =>
                [$metrics->successRate, '%'],

            MetricNames::SLOW_REQUEST_COUNT =>
                [$metrics->slowRequestCount, 'requests'],
        ];

        foreach ($definitions as $metricName => [$value, $unit]) {

            $snapshots[] = new MetricSnapshotData(
                category: 'apache',
                metricName: $metricName,
                metricValue: (float) $value,
                metricUnit: $unit,
                snapshotAt: $snapshotAt,
            );
        }

        if (
            $metrics->hasResponseTime &&
            $metrics->averageResponseTimeMs !== null
        ) {

            $snapshots[] = new MetricSnapshotData(
                category: 'apache',
                metricName: MetricNames::AVERAGE_RESPONSE_TIME,
                metricValue: (float) $metrics->averageResponseTimeMs,
                metricUnit: 'ms',
                snapshotAt: $snapshotAt,
            );
        }

        return $snapshots;
    }
}
