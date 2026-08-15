<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\NginxCollector;
use App\Services\Monitoring\NginxMonitoringService;
use Illuminate\View\View;

class NginxController extends Controller
{
    public function __construct(
        private readonly NginxCollector $collector,
        private readonly NginxMonitoringService $service,
    ) {}

    public function show(MonitoredServer $server): View
    {
        $parsed = $this->collector->collect($server);

        $metrics = $this->service->analyze($parsed);

        return view('servers.nginx', [
            'server' => $server,
            'metrics' => $metrics,
            'traffic' => $this->service->formattedTraffic(
                $metrics['totalTrafficBytes']
            ),
        ]);
    }
}