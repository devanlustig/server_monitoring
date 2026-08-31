<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\ApacheMonitoringService;
use App\Services\Monitoring\Collectors\ApacheCollector;
use App\Services\Monitoring\History\MetricHistoryQueryService;
use App\Services\Monitoring\Support\MetricNames;
use App\Services\Monitoring\Analytics\EndpointAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class ApacheController extends Controller
{
    public function __construct(
        private readonly ApacheCollector $collector,
        private readonly ApacheMonitoringService $service,
        private readonly MetricHistoryQueryService $history,
        private readonly EndpointAnalyticsService $analytics,
        private readonly \App\Services\Monitoring\EndpointSourceResolverFactory $resolverFactory,
    ){}

    public function show(MonitoredServer $server): View
    {
        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
        } catch (\Throwable $e) {
            logger()->error("Apache monitoring failed for server {$server->name}: " . $e->getMessage(), [
                'server_id' => $server->id,
                'exception' => $e,
            ]);
            $parsed = ['logFound' => false, 'entries' => []];
            $metrics = $this->service->analyze($parsed);
        }

        $metric = request()->get('metric',MetricNames::AVERAGE_RESPONSE_TIME);
        $history = $this->loadHistory($server,$metric);
        $analytics=$this->loadAnalytics($metrics->endpointAnalytics);

        // Resolve endpoint sources
        $endpoints = [];
        foreach ($analytics as $list) {
            foreach ($list as $item) {
                if (isset($item['endpoint'])) {
                    $endpoints[] = $item['endpoint'];
                }
            }
        }
        $endpoints = array_unique($endpoints);
        $resolvedSources = [];
        if (!empty($endpoints)) {
            $resolver = $this->resolverFactory->make('apache');
            if (method_exists($resolver, 'setVirtualHost')) {
                $vhosts = [];
                if (isset($parsed['entries']) && is_array($parsed['entries'])) {
                    foreach ($parsed['entries'] as $entry) {
                        if (!empty($entry->virtualHost)) {
                            $vhosts[$entry->virtualHost] = ($vhosts[$entry->virtualHost] ?? 0) + 1;
                        }
                    }
                }
                if (!empty($vhosts)) {
                    arsort($vhosts);
                    $resolver->setVirtualHost(key($vhosts));
                }
            }
            $resolved = $resolver->resolve($server, $endpoints);
            foreach ($resolved as $data) {
                $resolvedSources[$data->endpoint] = $data;
            }
        }

        return view('servers.apache', [
            'server' => $server,
            'metrics' => $metrics,
            'history' => $history,
            'analytics'=>$analytics,
            'resolvedSources' => $resolvedSources,
        ]);
    }

    public function refresh(MonitoredServer $server): JsonResponse
    {
        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
            $history = $this->loadHistory($server);

        } catch (\Throwable $e) {
            throw $e;
        }
        $analytics=$this->loadAnalytics($metrics->endpointAnalytics);

        // Resolve endpoint sources
        $endpoints = [];
        foreach ($analytics as $list) {
            foreach ($list as $item) {
                if (isset($item['endpoint'])) {
                    $endpoints[] = $item['endpoint'];
                }
            }
        }
        $endpoints = array_unique($endpoints);
        $resolvedSources = [];
        if (!empty($endpoints)) {
            $resolver = $this->resolverFactory->make('apache');
            $resolved = $resolver->resolve($server, $endpoints);
            foreach ($resolved as $data) {
                $resolvedSources[$data->endpoint] = $data;
            }
        }

        $html = view('servers.partials.apache-content',
        [
            'server'  => $server,
            'metrics' => $metrics,
            'history' => $history,
            'analytics' => $analytics,
            'resolvedSources' => $resolvedSources,
        ]
        )->render();

        return response()->json([
            'html' => $html,
            'metrics' => [
                'totalRequests' => $metrics->totalRequests,
                'requestsPerMinute' => $metrics->requestsPerMinute,
                'requestsPerHour' => $metrics->requestsPerHour,
                'totalTraffic' => $metrics->formattedTotalTraffic(),
                'averageResponseTimeMs' => $metrics->averageResponseTimeMs,
                'http2xx' => $metrics->http2xx,
                'http3xx' => $metrics->http3xx,
                'http4xx' => $metrics->http4xx,
                'http5xx' => $metrics->http5xx,
                'requestTimeline' => $metrics->requestTimeline,
            ],
        ]);
    }

    private function loadHistory(MonitoredServer $server,string $metric = MetricNames::AVERAGE_RESPONSE_TIME): array{

        return [
            'chart' => $this->history->chartLast24Hours(
                server: $server,
                category: 'apache',
                metricName: $metric,
            ),

            'summary' => $this->history->summaryLast24Hours(
                server: $server,
                category: 'apache',
                metricName: $metric,
            ),
        ];
    }

    private function loadAnalytics(array $endpointAnalytics): array
    {
        $collection=collect($endpointAnalytics);

        return[
            'topRequests'=>$this->analytics->topRequests($collection),
            'topSlow'=>$this->analytics->topSlow($collection),
            'topTraffic'=>$this->analytics->topTraffic($collection),
            'topErrors'=>$this->analytics->topErrors($collection),
        ];
    }

    public function history(Request $request,MonitoredServer $server
    ): JsonResponse {

        $metric = $request->get(
            'metric',
            MetricNames::AVERAGE_RESPONSE_TIME
        );

        $period = $request->get('period', '24h');
        [$from, $to] = $this->history->resolvePeriod($period);
        $chart = $this->history->chart(
            server: $server,
            category: 'apache',
            metricName: $metric,
            from: $from,
            to: $to,
        );
        $summary = $this->history->summary(
            server: $server,
            category: 'apache',
            metricName: $metric,
            from: $from,
            to: $to,
        );

        return response()->json([

            'summary' => [
                'current' => $summary->current,
                'average' => $summary->average,
                'maximum' => $summary->maximum,
                'minimum' => $summary->minimum,
                'trendPercent' => $summary->trendPercent,
                'difference' => $summary->difference,

            ],

            'chart' => [
                'labels' => $chart->labels,
                'values' => $chart->values,

            ],

        ]);
    }

}
