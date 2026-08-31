<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;

interface WebServerEndpointResolver
{
    /**
     * Resolve a list of endpoints to their sources on a server.
     *
     * @param MonitoredServer $server
     * @param string[] $endpoints
     * @return \App\Services\Monitoring\DTO\WebEndpointSourceData[]
     */
    public function resolve(MonitoredServer $server, array $endpoints): array;
}
