<?php

namespace App\Services\Monitoring\DTO;

class ApacheMetricsData
{
    public function __construct(
        public readonly int $totalRequests,
        public readonly float $requestsPerMinute,
        public readonly float $requestsPerHour,
        public readonly int $totalTrafficBytes,
        public readonly ?float $averageResponseTimeMs,
        public readonly int $http2xx,
        public readonly int $http3xx,
        public readonly int $http4xx,
        public readonly int $http5xx,
        public readonly bool $hasResponseTime,
        public readonly bool $logFound,
        public readonly ?string $logPath,
        public readonly array $topEndpoints,
        public readonly array $slowEndpoints,
        public readonly array $topClientIps,
        public readonly array $errorEndpoints,
        public readonly array $responseTimeDistribution,
        public readonly array $requestTimeline,
        public readonly float $healthScore = 100.0,
        public readonly float $errorRate = 0.0,
        public readonly float $successRate = 100.0,
        public readonly int $slowRequestCount = 0,
        public readonly array $peakRequestMinute = ['minute' => '-', 'count' => 0],
        public readonly float $averageRequestMinute = 0.0,
        public readonly array $highestTrafficMinute = ['minute' => '-', 'count' => 0],
        public readonly array $recommendations = [],
        public readonly array $entries = [],
    ) {}

    public function formattedTotalTraffic(): string
    {
        $bytes = $this->totalTrafficBytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
