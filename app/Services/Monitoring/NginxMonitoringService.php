<?php

namespace App\Services\Monitoring;

use App\Services\Monitoring\DTO\ApacheLogEntryData;
use Illuminate\Support\Collection;

class NginxMonitoringService
{
    public function analyze(array $parsedResult): array
    {
        $logFound = $parsedResult['logFound'] ?? false;

        /** @var ApacheLogEntryData[] $entries */
        $entries = $parsedResult['entries'] ?? [];

        if (! $logFound || empty($entries)) {
            return [
                'logFound' => $logFound,
                'totalRequests' => 0,
                'requestsPerMinute' => 0.0,
                'requestsPerHour' => 0.0,
                'totalTrafficBytes' => 0,
                'http2xx' => 0,
                'http3xx' => 0,
                'http4xx' => 0,
                'http5xx' => 0,
                'errorRate' => 0.0,
                'successRate' => 100.0,
                'topEndpoints' => [],
                'topClientIps' => [],
                'errorEndpoints' => [],
                'requestTimeline' => [
                    'labels' => [],
                    'data' => [],
                ],
                'healthScore' => 100.0,
                'entries' => [],
            ];
        }

        $totalRequests = count($entries);

        $totalTrafficBytes = 0;

        $http2xx = 0;
        $http3xx = 0;
        $http4xx = 0;
        $http5xx = 0;

        $firstTime = null;
        $lastTime = null;

        $endpointStats = [];
        $clientStats = [];
        $errorStats = [];
        $timelineMinutes = [];

        foreach ($entries as $entry) {

            /*
             * Traffic
             */
            $totalTrafficBytes += $entry->bytes;

            /*
             * HTTP Status
             */
            if ($entry->statusCode >= 200 && $entry->statusCode < 300) {
                $http2xx++;
            } elseif ($entry->statusCode >= 300 && $entry->statusCode < 400) {
                $http3xx++;
            } elseif ($entry->statusCode >= 400 && $entry->statusCode < 500) {
                $http4xx++;
            } elseif ($entry->statusCode >= 500 && $entry->statusCode < 600) {
                $http5xx++;
            }

            /*
             * Time window
             */
            if ($entry->dateTime) {

                $timestamp = $entry->dateTime->getTimestamp();

                if ($firstTime === null || $timestamp < $firstTime) {
                    $firstTime = $timestamp;
                }

                if ($lastTime === null || $timestamp > $lastTime) {
                    $lastTime = $timestamp;
                }

                $minuteKey = $entry->dateTime->format('H:i');

                $timelineMinutes[$minuteKey] =
                    ($timelineMinutes[$minuteKey] ?? 0) + 1;
            }

            /*
             * Endpoint statistics
             */
            if (! isset($endpointStats[$entry->endpoint])) {
                $endpointStats[$entry->endpoint] = [
                    'endpoint' => $entry->endpoint,
                    'requests' => 0,
                    'bytes' => 0,
                    '2xx' => 0,
                    '3xx' => 0,
                    '4xx' => 0,
                    '5xx' => 0,
                    'lastAccess' => null,
                ];
            }

            $endpointStats[$entry->endpoint]['requests']++;
            $endpointStats[$entry->endpoint]['bytes'] += $entry->bytes;
            $endpointStats[$entry->endpoint]['lastAccess'] =
                $entry->dateTime;

            if ($entry->statusCode >= 500) {
                $endpointStats[$entry->endpoint]['5xx']++;
            } elseif ($entry->statusCode >= 400) {
                $endpointStats[$entry->endpoint]['4xx']++;
            } elseif ($entry->statusCode >= 300) {
                $endpointStats[$entry->endpoint]['3xx']++;
            } else {
                $endpointStats[$entry->endpoint]['2xx']++;
            }

            /*
             * Client IP statistics
             */
            if (! isset($clientStats[$entry->ip])) {
                $clientStats[$entry->ip] = [
                    'ip' => $entry->ip,
                    'requests' => 0,
                    'totalBytes' => 0,
                ];
            }

            $clientStats[$entry->ip]['requests']++;
            $clientStats[$entry->ip]['totalBytes'] += $entry->bytes;

            /*
             * Error statistics
             */
            if ($entry->statusCode >= 400) {

                if (! isset($errorStats[$entry->endpoint])) {
                    $errorStats[$entry->endpoint] = [
                        'endpoint' => $entry->endpoint,
                        'status404' => 0,
                        'status500' => 0,
                        'status503' => 0,
                        'totalErrors' => 0,
                    ];
                }

                $errorStats[$entry->endpoint]['totalErrors']++;

                if ($entry->statusCode === 404) {
                    $errorStats[$entry->endpoint]['status404']++;
                } elseif ($entry->statusCode === 500) {
                    $errorStats[$entry->endpoint]['status500']++;
                } elseif ($entry->statusCode === 503) {
                    $errorStats[$entry->endpoint]['status503']++;
                }
            }
        }

        /*
         * Request rate
         */
        $timeWindowMinutes = 1.0;

        if (
            $firstTime !== null &&
            $lastTime !== null &&
            $lastTime > $firstTime
        ) {
            $timeWindowMinutes = max(
                1.0,
                ($lastTime - $firstTime) / 60
            );
        }

        $requestsPerMinute = round(
            $totalRequests / $timeWindowMinutes,
            2
        );

        $requestsPerHour = round(
            $requestsPerMinute * 60,
            2
        );

        /*
         * Top endpoints
         */
        $topEndpoints = collect($endpointStats)
            ->sortByDesc('requests')
            ->values()
            ->take(10)
            ->all();

        /*
         * Top client IP
         */
        $topClientIps = collect($clientStats)
            ->sortByDesc('requests')
            ->values()
            ->take(10)
            ->all();

        /*
         * Error endpoints
         */
        $errorEndpoints = collect($errorStats)
            ->sortByDesc('totalErrors')
            ->values()
            ->take(10)
            ->all();

        /*
         * Timeline
         */
        ksort($timelineMinutes);

        $requestTimeline = [
            'labels' => array_keys($timelineMinutes),
            'data' => array_values($timelineMinutes),
        ];

        /*
         * Success / Error rate
         */
        $totalErrors = $http4xx + $http5xx;
        $totalSuccesses = $http2xx + $http3xx;

        $errorRate = $totalRequests > 0
            ? round(($totalErrors / $totalRequests) * 100, 2)
            : 0.0;

        $successRate = $totalRequests > 0
            ? round(($totalSuccesses / $totalRequests) * 100, 2)
            : 100.0;

        /*
         * Health score
         */
        $healthScore = 100.0;

        if ($totalRequests > 0) {

            $p5xx = ($http5xx / $totalRequests) * 100;
            $p4xx = ($http4xx / $totalRequests) * 100;

            $healthScore -= $p5xx * 5.0;
            $healthScore -= $p4xx * 1.5;
        }

        $healthScore = max(
            0.0,
            min(100.0, round($healthScore, 1))
        );

        return [
            'logFound' => true,
            'totalRequests' => $totalRequests,
            'requestsPerMinute' => $requestsPerMinute,
            'requestsPerHour' => $requestsPerHour,
            'totalTrafficBytes' => $totalTrafficBytes,

            'http2xx' => $http2xx,
            'http3xx' => $http3xx,
            'http4xx' => $http4xx,
            'http5xx' => $http5xx,

            'errorRate' => $errorRate,
            'successRate' => $successRate,

            'topEndpoints' => $topEndpoints,
            'topClientIps' => $topClientIps,
            'errorEndpoints' => $errorEndpoints,

            'requestTimeline' => $requestTimeline,

            'healthScore' => $healthScore,

            'entries' => $entries,
        ];
    }

    public function formattedTraffic(int $bytes): string
    {
        if ($bytes >= 1024 ** 3) {
            return round($bytes / (1024 ** 3), 2) . ' GB';
        }

        if ($bytes >= 1024 ** 2) {
            return round($bytes / (1024 ** 2), 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}