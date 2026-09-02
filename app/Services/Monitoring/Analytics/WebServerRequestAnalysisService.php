<?php

namespace App\Services\Monitoring\Analytics;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\ApacheCollector;
use App\Services\Monitoring\Collectors\NginxCollector;
use App\Services\Monitoring\EndpointSourceResolverFactory;
use Carbon\Carbon;
use Throwable;

class WebServerRequestAnalysisService
{
    public function __construct(
        private readonly ApacheCollector $apacheCollector,
        private readonly NginxCollector $nginxCollector,
        private readonly EndpointSourceResolverFactory $resolverFactory,
    ) {}

    public function analyze(MonitoredServer $server, string $timestamp): array
    {
        $targetTime = Carbon::parse($timestamp);
        $targetTs = $targetTime->getTimestamp();
        $windowStartTs = $targetTs - 120;
        $windowEndTs = $targetTs + 120;

        $type = strtolower($server->web_server ?? 'nginx');
        $entries = [];

        try {
            if ($type === 'apache') {
                $parsed = $this->apacheCollector->collect($server);
                $entries = $parsed['entries'] ?? [];
            } else {
                $parsed = $this->nginxCollector->collect($server);
                $entries = $parsed['entries'] ?? [];
            }
        } catch (Throwable $e) {
            logger()->warning("Failed to collect access log for request analysis on server {$server->name}: " . $e->getMessage());
        }

        // Filter entries within primary 2-minute window
        $filtered = array_values(array_filter($entries, function ($entry) use ($windowStartTs, $windowEndTs) {
            if (!isset($entry->dateTime)) return false;
            $ts = $entry->dateTime->getTimestamp();
            return $ts >= $windowStartTs && $ts <= $windowEndTs;
        }));

        // Fallback: If no entries in 2m window, try 5m window
        if (empty($filtered) && !empty($entries)) {
            $windowStartTs = $targetTs - 300;
            $windowEndTs = $targetTs + 300;
            $filtered = array_values(array_filter($entries, function ($entry) use ($windowStartTs, $windowEndTs) {
                if (!isset($entry->dateTime)) return false;
                $ts = $entry->dateTime->getTimestamp();
                return $ts >= $windowStartTs && $ts <= $windowEndTs;
            }));
        }

        $totalRequests = count($filtered);
        $requestsPerMinute = round($totalRequests / 2.0, 1);

        if ($totalRequests === 0) {
            return [
                'timestamp' => $targetTime->toDateTimeString(),
                'totalRequests' => 0,
                'requestsPerMinute' => 0,
                'endpoints' => [],
            ];
        }

        // Group by endpoint
        $grouped = [];
        foreach ($filtered as $entry) {
            $ep = $entry->endpoint ?? '/';
            if (!isset($grouped[$ep])) {
                $grouped[$ep] = [
                    'endpoint' => $ep,
                    'requests' => 0,
                    'http2xx' => 0,
                    'http3xx' => 0,
                    'http4xx' => 0,
                    'http5xx' => 0,
                ];
            }
            $grouped[$ep]['requests']++;
            $code = $entry->statusCode;
            if ($code >= 200 && $code < 300) $grouped[$ep]['http2xx']++;
            elseif ($code >= 300 && $code < 400) $grouped[$ep]['http3xx']++;
            elseif ($code >= 400 && $code < 500) $grouped[$ep]['http4xx']++;
            elseif ($code >= 500 && $code < 600) $grouped[$ep]['http5xx']++;
        }

        // Sort by request count descending
        usort($grouped, fn($a, $b) => $b['requests'] <=> $a['requests']);

        // Top endpoints
        $topEndpoints = array_slice($grouped, 0, 15);

        // Resolve sources
        $endpointNames = array_column($topEndpoints, 'endpoint');
        $resolvedSources = [];

        try {
            $resolver = $this->resolverFactory->make($type);
            if ($type === 'apache' && method_exists($resolver, 'setVirtualHost')) {
                $vhosts = [];
                foreach ($filtered as $entry) {
                    if (!empty($entry->virtualHost)) {
                        $vhosts[$entry->virtualHost] = ($vhosts[$entry->virtualHost] ?? 0) + 1;
                    }
                }
                if (!empty($vhosts)) {
                    arsort($vhosts);
                    $resolver->setVirtualHost(key($vhosts));
                }
            }
            $resolved = $resolver->resolve($server, $endpointNames);
            foreach ($resolved as $data) {
                $resolvedSources[$data->endpoint] = $data;
            }
        } catch (Throwable $e) {
            logger()->warning("Resolver failed during request analysis on server {$server->name}: " . $e->getMessage());
        }

        $endpointsData = [];
        foreach ($topEndpoints as $item) {
            $ep = $item['endpoint'];
            $pct = round(($item['requests'] / $totalRequests) * 100, 1);
            $sourceData = $resolvedSources[$ep] ?? null;

            $sourceType = 'unknown';
            $sourcePath = 'Unknown / Not Resolved';
            $application = 'Unknown / Not Resolved';

            if ($sourceData && $sourceData->resolved) {
                $sourceType = $sourceData->sourceType; // 'root', 'alias', 'proxy'
                $sourcePath = $sourceData->sourcePath ?? 'Unknown / Not Resolved';
                $application = $sourceType === 'proxy'
                    ? ($sourceData->proxyTarget ?? 'Proxy Target')
                    : ($sourceData->sourcePath ?? 'Document Root');
            }

            $endpointsData[] = [
                'endpoint' => $ep,
                'requests' => $item['requests'],
                'percentage' => $pct,
                'http2xx' => $item['http2xx'],
                'http3xx' => $item['http3xx'],
                'http4xx' => $item['http4xx'],
                'http5xx' => $item['http5xx'],
                'sourceType' => $sourceType,
                'source' => $sourcePath,
                'application' => $application,
            ];
        }

        return [
            'timestamp' => $targetTime->toDateTimeString(),
            'totalRequests' => $totalRequests,
            'requestsPerMinute' => $requestsPerMinute,
            'endpoints' => $endpointsData,
        ];
    }
}
