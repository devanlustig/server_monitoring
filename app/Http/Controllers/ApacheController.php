<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\ApacheMonitoringService;
use App\Services\Monitoring\Collectors\ApacheCollector;
use App\Services\Monitoring\History\MetricHistoryQueryService;
use App\Services\Monitoring\Support\MetricNames;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class ApacheController extends Controller
{
    public function __construct(
        private readonly ApacheCollector $collector,
        private readonly ApacheMonitoringService $service,
        private readonly MetricHistoryQueryService $history,
    ) {}

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

        $history = $this->loadHistory($server);

        return view('servers.apache', [
            'server' => $server,
            'metrics' => $metrics,
            'history' => $history,
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

        $html = view('servers.partials.apache-content',
        [
            'server'  => $server,
            'metrics' => $metrics,
            'history' => $history,
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

    private function loadHistory(MonitoredServer $server): array
    {
        return [
            'chart' => $this->history->chartLast24Hours(
                server: $server,
                category: 'apache',
                metricName: MetricNames::AVERAGE_RESPONSE_TIME,
            ),

            'summary' => $this->history->summaryLast24Hours(
                server: $server,
                category: 'apache',
                metricName: MetricNames::AVERAGE_RESPONSE_TIME,
            ),
        ];
    }

    public function history(Request $request,MonitoredServer $server
    ): JsonResponse {

        $period = $request->get('period', '24h');
        [$from, $to] = $this->history->resolvePeriod($period);
        $chart = $this->history->chart(
            server: $server,
            category: 'apache',
            metricName: MetricNames::AVERAGE_RESPONSE_TIME,
            from: $from,
            to: $to,
        );
        $summary = $this->history->summary(
            server: $server,
            category: 'apache',
            metricName: MetricNames::AVERAGE_RESPONSE_TIME,
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
