<?php

namespace App\Services\Monitoring;

use App\Services\Monitoring\DTO\ApacheLogEntryData;
use App\Services\Monitoring\DTO\ApacheMetricsData;
use App\Services\Monitoring\Analytics\EndpointAnalyticsService;
use Illuminate\Support\Collection;

class ApacheMonitoringService
{

    private const RESPONSE_EXCELLENT_MS = 100;
    private const RESPONSE_GOOD_MS = 300;
    private const RESPONSE_ACCEPTABLE_MS = 1000;
    private const RESPONSE_SLOW_MS = 3000;

    public function __construct(
        private readonly EndpointAnalyticsService $endpointAnalytics
    ){}

    public function analyze(array $parsedResult): ApacheMetricsData
    {
        $logFound = $parsedResult['logFound'] ?? false;
        /** @var ApacheLogEntryData[] $entries */
        $entries = $parsedResult['entries'] ?? [];

        if (! $logFound || empty($entries)) {
            return new ApacheMetricsData(
                totalRequests: 0,
                requestsPerMinute: 0.0,
                requestsPerHour: 0.0,
                totalTrafficBytes: 0,
                averageResponseTimeMs: null,
                http2xx: 0,
                http3xx: 0,
                http4xx: 0,
                http5xx: 0,
                hasResponseTime: false,
                logFound: $logFound,
                logPath: null,
                topEndpoints: [],
                endpointAnalytics:[],
                slowEndpoints:[],
                topClientIps: [],
                errorEndpoints: [],
                responseTimeDistribution: [
                    'under100ms' => 0,
                    'between100and300ms' => 0,
                    'between300and500ms' => 0,
                    'between500and1000ms' => 0,
                    'over1000ms' => 0,
                ],
                requestTimeline: ['labels' => [], 'data' => []],
                entries: [],
            );
        }

        $totalRequests = count($entries);
        $totalTrafficBytes = 0;
        $http2xx = 0;
        $http3xx = 0;
        $http4xx = 0;
        $http5xx = 0;
        $totalResponseTimeMs = 0.0;
        $responseTimeCount = 0;

        $under100ms = 0;
        $between100and300ms = 0;
        $between300and500ms = 0;
        $between500and1000ms = 0;
        $over1000ms = 0;

        $firstTime = null;
        $lastTime = null;

        $endpointStats = [];
        $clientStats = [];
        $errorStats = [];
        $timelineMinutes = [];
        $slowEntries = [];
        $slowEndpoints=[];
        $slowRequestThreshold = config('monitoring.thresholds.slow_request_ms',3000);

        foreach ($entries as $entry) {
            $totalTrafficBytes += $entry->bytes;

            // Status Code groups
            if ($entry->statusCode >= 200 && $entry->statusCode < 300) {
                $http2xx++;
            } elseif ($entry->statusCode >= 300 && $entry->statusCode < 400) {
                $http3xx++;
            } elseif ($entry->statusCode >= 400 && $entry->statusCode < 500) {
                $http4xx++;
            } elseif ($entry->statusCode >= 500 && $entry->statusCode < 600) {
                $http5xx++;
            }

            // Timestamps for rate calculation & timeline
            if ($entry->dateTime) {
                $ts = $entry->dateTime->getTimestamp();
                if ($firstTime === null || $ts < $firstTime) {
                    $firstTime = $ts;
                }
                if ($lastTime === null || $ts > $lastTime) {
                    $lastTime = $ts;
                }

                $minuteKey = $entry->dateTime->format('H:i');
                $timelineMinutes[$minuteKey] = ($timelineMinutes[$minuteKey] ?? 0) + 1;
            }

            // Response time tracking
            if ($entry->responseTimeMs !== null) {
                $responseTimeCount++;
                $totalResponseTimeMs += $entry->responseTimeMs;
                $rt = $entry->responseTimeMs;

                if ($rt < self::RESPONSE_EXCELLENT_MS) {
                    $under100ms++;
                } elseif ($rt <= self::RESPONSE_GOOD_MS) {
                    $between100and300ms++;
                } elseif ($rt <= self::RESPONSE_ACCEPTABLE_MS) {
                    $between300and500ms++;
                } elseif ($rt <= self::RESPONSE_SLOW_MS) {
                    $between500and1000ms++;
                } else {
                    $over1000ms++;
                }
            }

            if($entry->responseTimeMs!==null&&$entry->responseTimeMs>=$slowRequestThreshold){
                $slowEndpoints[]=[
                    'endpoint'=>$entry->endpoint,
                    'method'=>$entry->method,
                    'responseTimeMs'=>$entry->responseTimeMs,
                    'statusCode'=>$entry->statusCode,
                    'timestamp'=>$entry->timestamp,
                    'ip'=>$entry->ip,
                ];
            }

            // Endpoint stats aggregation
            if (!isset($endpointStats[$entry->endpoint])) {
                $endpointStats[$entry->endpoint]=[
                    'endpoint'=>$entry->endpoint,
                    'requests'=>0,
                    'totalResponseMs'=>0,
                    'maxResponseMs'=>0,
                    'minResponseMs'=>null,
                    'bytes'=>0,
                    '2xx'=>0,
                    '3xx'=>0,
                    '4xx'=>0,
                    '5xx'=>0,
                    'lastAccess'=>null,
                    'hasRt'=>false,
                ];
            }
            $endpointStats[$entry->endpoint]['requests']++;
            $endpointStats[$entry->endpoint]['bytes']+=$entry->bytes;
            $endpointStats[$entry->endpoint]['lastAccess']=$entry->dateTime;

            if($entry->responseTimeMs!==null){
                $endpointStats[$entry->endpoint]['hasRt']=true;
                $endpointStats[$entry->endpoint]['totalResponseMs']+=$entry->responseTimeMs;
                if($endpointStats[$entry->endpoint]['minResponseMs']===null||$entry->responseTimeMs<$endpointStats[$entry->endpoint]['minResponseMs']){
                    $endpointStats[$entry->endpoint]['minResponseMs']=$entry->responseTimeMs;
                }
                if($entry->responseTimeMs>$endpointStats[$entry->endpoint]['maxResponseMs']){
                    $endpointStats[$entry->endpoint]['maxResponseMs']=$entry->responseTimeMs;
                }
            }

            switch(true){
                case $entry->statusCode>=500:
                    $endpointStats[$entry->endpoint]['5xx']++;
                    break;

                case $entry->statusCode>=400:
                    $endpointStats[$entry->endpoint]['4xx']++;
                    break;

                case $entry->statusCode>=300:
                    $endpointStats[$entry->endpoint]['3xx']++;
                    break;

                default:
                    $endpointStats[$entry->endpoint]['2xx']++;
                    break;
            }

            // Client IP stats aggregation
            if (! isset($clientStats[$entry->ip])) {
                $clientStats[$entry->ip] = [
                    'ip' => $entry->ip,
                    'requests' => 0,
                    'totalBytes' => 0,
                ];
            }
            $clientStats[$entry->ip]['requests']++;
            $clientStats[$entry->ip]['totalBytes'] += $entry->bytes;

            // Error stats aggregation
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

        usort($slowEndpoints,fn($a,$b)=>$b['responseTimeMs']<=>$a['responseTimeMs']);
        $slowEndpoints=array_slice($slowEndpoints,0,20);

        // Rates
        $timeWindowMinutes = 1.0;
        if ($firstTime !== null && $lastTime !== null && $lastTime > $firstTime) {
            $timeWindowMinutes = max(1.0, ($lastTime - $firstTime) / 60.0);
        }
        $requestsPerMinute = round($totalRequests / $timeWindowMinutes, 2);
        $requestsPerHour = round($requestsPerMinute * 60, 2);

        $hasResponseTime = $responseTimeCount > 0;
        $averageResponseTimeMs = $hasResponseTime ? round($totalResponseTimeMs / $responseTimeCount, 2) : null;

        // analyze
        $endpointAnalytics=collect($endpointStats)->map(function($item){
        $avg=$item['hasRt']&&$item['requests']>0
            ?round($item['totalResponseMs']/$item['requests'],2)
            :null;
        return[
                'endpoint'=>$item['endpoint'],
                'requests'=>$item['requests'],
                'avgResponseMs'=>$avg,
                'minResponseMs'=>$item['minResponseMs'],
                'maxResponseMs'=>$item['maxResponseMs'],
                'bytes'=>$item['bytes'],
                '2xx'=>$item['2xx'],
                '3xx'=>$item['3xx'],
                '4xx'=>$item['4xx'],
                '5xx'=>$item['5xx'],
                'lastAccess'=>$item['lastAccess'],
            ];
        });

        $topEndpoints=$this->endpointAnalytics->topRequests($endpointAnalytics);

        // Sort Top Client IPs
        usort($clientStats, fn ($a, $b) => $b['requests'] <=> $a['requests']);
        $topClientIps = array_slice($clientStats, 0, 10);

        // Sort Error Endpoints
        usort($errorStats, fn ($a, $b) => $b['totalErrors'] <=> $a['totalErrors']);
        $errorEndpoints = array_slice($errorStats, 0, 10);

        // Request Timeline (sort chronological)
        ksort($timelineMinutes);
        $requestTimeline = [
            'labels' => array_keys($timelineMinutes),
            'data' => array_values($timelineMinutes),
        ];

        // 1. Success & Error Rates
        $totalErrors = $http4xx + $http5xx;
        $totalSuccesses = $http2xx + $http3xx;
        $errorRate = $totalRequests > 0 ? round(($totalErrors / $totalRequests) * 100, 2) : 0.0;
        $successRate = $totalRequests > 0 ? round(($totalSuccesses / $totalRequests) * 100, 2) : 100.0;

        // 2. Slow Request Count (>500ms)
        $slowRequestCount = count($slowEndpoints);

        // 3. Peak Request Minute & Average Request Minute
        $peakMinuteKey = '-';
        $peakCount = 0;
        foreach ($timelineMinutes as $min => $cnt) {
            if ($cnt > $peakCount) {
                $peakCount = $cnt;
                $peakMinuteKey = $min;
            }
        }
        $peakRequestMinute = ['minute' => $peakMinuteKey, 'count' => $peakCount];
        $highestTrafficMinute = $peakRequestMinute; // Peak request minute represents highest volume minute
        $averageRequestMinute = count($timelineMinutes) > 0 ? round(array_sum($timelineMinutes) / count($timelineMinutes), 2) : $requestsPerMinute;

        // 4. Health Score Calculation (0 - 100)
        $healthScore = 100.0;
        if ($totalRequests > 0) {
            $p5xx = ($http5xx / $totalRequests) * 100;
            $p4xx = ($http4xx / $totalRequests) * 100;
            //$pSlow = ($over1000ms / $totalRequests) * 100;
            $pSlow = ($slowRequestCount / $totalRequests) * 100;

            $healthScore -= ($p5xx * 5.0); // Penalty for 5xx errors
            $healthScore -= ($p4xx * 1.5); // Penalty for 4xx errors
            $healthScore -= ($pSlow * 2.0); // Penalty for very slow requests (>3s)

            if ($averageResponseTimeMs !== null && $averageResponseTimeMs > self::RESPONSE_SLOW_MS) {
                $healthScore -= 10.0;
            }
        }
        $healthScore = max(0.0, min(100.0, round($healthScore, 1)));

        // 5. Smart Recommendations
        $recommendations = [];
        if ($http5xx > 0) {
            $recommendations[] = [
                'type' => 'danger',
                'icon' => 'bi-exclamation-octagon-fill',
                'title' => 'Server Error Detected',
                'message' => "Terdeteksi <strong>{$http5xx}</strong> request dengan status HTTP 5xx. Periksa error log aplikasi backend.",
            ];
        }
        if ($errorRate > 5.0) {
            $recommendations[] = [
                'type' => 'warning',
                'icon' => 'bi-exclamation-triangle-fill',
                'title' => 'Tingkat Error Tinggi',
                'message' => "Error rate mencapai <strong>{$errorRate}%</strong>. Tinjau tabel <em>Error Endpoints</em> untuk mendeteksi link rusak atau 404.",
            ];
        }
        if ($slowRequestCount > 0) {
            $recommendations[] = [
                'type' => 'warning',
                'icon' => 'bi-hourglass-bottom',
                'title' => 'Request Lambat (> 3 Detik)',
                'message' => "Terdapat <strong>{$slowRequestCount}</strong> request dengan response time lebih dari 3 detik. Pertimbangkan optimasi query database atau caching.",
            ];
        }
        if ($hasResponseTime && $averageResponseTimeMs > self::RESPONSE_SLOW_MS) {
            $recommendations[] = [
                'type' => 'info',
                'icon' => 'bi-speedometer2',
                'title' => 'Waktu Respon Cukup Tinggi',
                'message' => "Rata-rata waktu respon adalah <strong>{$averageResponseTimeMs} ms</strong>. Periksa slow endpoints untuk analisa beban server.",
            ];
        }
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'icon' => 'bi-check-circle-fill',
                'title' => 'Sistem Berjalan Optimal',
                'message' => "Kinerja web server Apache dalam kondisi baik dengan Health Score <strong>{$healthScore}%</strong> dan Success Rate <strong>{$successRate}%</strong>.",
            ];
        }

        return new ApacheMetricsData(
            totalRequests: $totalRequests,
            requestsPerMinute: $requestsPerMinute,
            requestsPerHour: $requestsPerHour,
            totalTrafficBytes: $totalTrafficBytes,
            averageResponseTimeMs: $averageResponseTimeMs,
            http2xx: $http2xx,
            http3xx: $http3xx,
            http4xx: $http4xx,
            http5xx: $http5xx,
            hasResponseTime: $hasResponseTime,
            logFound: true,
            logPath: null,
            topEndpoints:$topEndpoints,
            endpointAnalytics:$endpointAnalytics->values()->all(),
            slowEndpoints:$slowEndpoints,
            topClientIps: $topClientIps,
            errorEndpoints: $errorEndpoints,
            responseTimeDistribution: [
                'under100ms' => $under100ms,
                'between100and300ms' => $between100and300ms,
                'between300and500ms' => $between300and500ms,
                'between500and1000ms' => $between500and1000ms,
                'over1000ms' => $over1000ms,
            ],
            requestTimeline: $requestTimeline,
            healthScore: $healthScore,
            errorRate: $errorRate,
            successRate: $successRate,
            slowRequestCount: $slowRequestCount,
            peakRequestMinute: $peakRequestMinute,
            averageRequestMinute: $averageRequestMinute,
            highestTrafficMinute: $highestTrafficMinute,
            recommendations: $recommendations,
            entries: $entries,
        );
    }
}
