<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\ApacheMonitoringService;
use App\Services\Monitoring\Collectors\ApacheCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ApacheController extends Controller
{
    public function __construct(
        private readonly ApacheCollector $collector,
        private readonly ApacheMonitoringService $service,
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
        return view('servers.apache', compact('server', 'metrics'));
    }

    public function refresh(MonitoredServer $server): JsonResponse
    {
        try {
            $parsed = $this->collector->collect($server);
            $metrics = $this->service->analyze($parsed);
        } catch (\Throwable $e) {
            logger()->error("Apache refresh failed for server {$server->name}: " . $e->getMessage(), [
                'server_id' => $server->id,
                'exception' => $e,
            ]);
            $parsed = ['logFound' => false, 'entries' => []];
            $metrics = $this->service->analyze($parsed);
        }
        $html = view('servers.partials.apache-content', compact('server', 'metrics'))->render();
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

}
