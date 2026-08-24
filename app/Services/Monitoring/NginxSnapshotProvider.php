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

        if (! ($parsed['logFound'] ?? false)) {
            return [];
        }

        $snapshotAt = now();
        $nowTimestamp = $snapshotAt->getTimestamp();
        $twoMinutesAgoTimestamp = $nowTimestamp - 120;

        $intervalEntries = array_filter(
            $parsed['entries'] ?? [],
            function ($entry) use ($twoMinutesAgoTimestamp, $nowTimestamp) {
                if (!isset($entry->dateTime)) {
                    return false;
                }
                $ts = $entry->dateTime->getTimestamp();
                return $ts >= $twoMinutesAgoTimestamp && $ts <= $nowTimestamp;
            }
        );

        $intervalRequests = count($intervalEntries);
        $totalTrafficBytes = 0;
        $http4xx = 0;
        $http5xx = 0;

        foreach ($intervalEntries as $entry) {
            $totalTrafficBytes += $entry->bytes;
            $code = $entry->statusCode;
            if ($code >= 400 && $code < 500) {
                $http4xx++;
            } elseif ($code >= 500 && $code < 600) {
                $http5xx++;
            }
        }

        $requestsPerMinute = $intervalRequests / 2.0;
        $requestsPerHour = $requestsPerMinute * 60.0;

        $errors = $http4xx + $http5xx;
        $errorRate = $intervalRequests > 0 ? round(($errors / $intervalRequests) * 100, 2) : 0.0;
        $successRate = 100.0 - $errorRate;

        $snapshots = [];

        $definitions = [
            MetricNames::TOTAL_REQUESTS =>
                [$intervalRequests, 'requests'],

            MetricNames::REQUESTS_PER_MINUTE =>
                [$requestsPerMinute, 'req/min'],

            MetricNames::REQUESTS_PER_HOUR =>
                [$requestsPerHour, 'req/hour'],

            MetricNames::TOTAL_TRAFFIC =>
                [$totalTrafficBytes, 'bytes'],

            MetricNames::ERROR_RATE =>
                [$errorRate, '%'],

            MetricNames::SUCCESS_RATE =>
                [$successRate, '%'],
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
