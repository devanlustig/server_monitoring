<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\NginxCollector;
use App\Services\Monitoring\NginxMonitoringService;
use App\Services\Monitoring\History\MetricHistoryQueryService;
use App\Services\Monitoring\Support\MetricNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NginxController extends Controller
{
    public function __construct(
        private readonly NginxCollector $collector,
        private readonly NginxMonitoringService $service,
        private readonly MetricHistoryQueryService $history,
    ) {}

    public function show(MonitoredServer $server): View
    {
        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
        } catch (\Throwable $e) {
            logger()->error("Nginx monitoring failed for server {$server->name}: " . $e->getMessage(), [
                'server_id' => $server->id,
                'exception' => $e,
            ]);
            $parsed = ['logFound' => false, 'entries' => []];
            $metrics = $this->service->analyze($parsed);
        }

        $metric = request()->get('metric', MetricNames::AVERAGE_RESPONSE_TIME);
        $history = $this->loadHistory($server, $metric);

        return view('servers.nginx', [
            'server' => $server,
            'metrics' => $metrics,
            'history' => $history,
            'traffic' => $this->service->formattedTraffic($metrics['totalTrafficBytes']),
        ]);
    }

    public function refresh(MonitoredServer $server): JsonResponse
    {
        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
            $history = $this->loadHistory($server);
        } catch (\Throwable $e) {
            logger()->error("Nginx refresh failed for server {$server->name}: " . $e->getMessage(), [
                'server_id' => $server->id,
                'exception' => $e,
            ]);
            $parsed = ['logFound' => false, 'entries' => []];
            $metrics = $this->service->analyze($parsed);
            $history = $this->loadHistory($server);
        }

        $html = view('servers.partials.nginx-content', [
            'server' => $server,
            'metrics' => $metrics,
            'history' => $history,
            'traffic' => $this->service->formattedTraffic($metrics['totalTrafficBytes']),
        ])->render();

        return response()->json([
            'html' => $html,
            'metrics' => [
                'totalRequests' => $metrics['totalRequests'],
                'requestsPerMinute' => $metrics['requestsPerMinute'],
                'requestsPerHour' => $metrics['requestsPerHour'],
                'totalTraffic' => $this->service->formattedTraffic($metrics['totalTrafficBytes']),
                'averageResponseTimeMs' => null, // Nginx doesn't log response time by default in combined log
                'http2xx' => $metrics['http2xx'],
                'http3xx' => $metrics['http3xx'],
                'http4xx' => $metrics['http4xx'],
                'http5xx' => $metrics['http5xx'],
                'requestTimeline' => $metrics['requestTimeline'],
            ],
        ]);
    }

    public function history(Request $request, MonitoredServer $server): JsonResponse
    {
        $metric = $request->get('metric', MetricNames::AVERAGE_RESPONSE_TIME);
        $period = $request->get('period', '24h');

        [$from, $to] = $this->history->resolvePeriod($period);
        $chart = $this->history->chart(
            server: $server,
            category: 'nginx',
            metricName: $metric,
            from: $from,
            to: $to,
        );
        $summary = $this->history->summary(
            server: $server,
            category: 'nginx',
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

    private function loadHistory(MonitoredServer $server, string $metric = MetricNames::AVERAGE_RESPONSE_TIME): array
    {
        return [
            'chart' => $this->history->chartLast24Hours(
                server: $server,
                category: 'nginx',
                metricName: $metric,
            ),
            'summary' => $this->history->summaryLast24Hours(
                server: $server,
                category: 'nginx',
                metricName: $metric,
            ),
        ];
    }
}