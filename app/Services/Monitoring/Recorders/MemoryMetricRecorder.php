<?php

namespace App\Services\Monitoring\Recorders;

use App\Models\MemoryMetric;
use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\MemoryMetricData;

class MemoryMetricRecorder
{
    public function record(
        MonitoredServer $server,
        MemoryMetricData $metric
    ): MemoryMetric {

        return MemoryMetric::create([

            'server_id'      => $server->id,

            'total'          => $metric->total,

            'used'           => $metric->used,

            'free'           => $metric->free,

            'shared'         => $metric->shared,

            'cache'          => $metric->cache,

            'available'      => $metric->available,

            'usage_percent'  => $metric->usagePercent,

            'collected_at'   => now(),

        ]);
    }
}