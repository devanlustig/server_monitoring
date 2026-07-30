<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\PostgreSqlIncidentService;

class PostgreSqlIncidentController extends Controller
{
    public function capture(
        MonitoredServer $server,
        PostgreSqlIncidentService $incident,
    ) {

        return $incident->capture($server);

    }
}