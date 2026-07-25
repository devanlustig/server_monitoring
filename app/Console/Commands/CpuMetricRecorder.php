<?php

namespace App\Services\Monitoring\Recorders;

use App\Models\CpuMetric;
use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\CpuMetricData;

class CpuMetricRecorder
{
    public function record(
        MonitoredServer $server,
        CpuMetricData $metric
    ): CpuMetric {

        return CpuMetric::create([
            'server_id'      => $server->id,
            'hostname'       => $server->system_hostname,
            'usage_percent'  => $metric->usagePercent,
            'load_1'         => $metric->load1,
            'load_5'         => $metric->load5,
            'load_15'        => $metric->load15,
            'core_count'     => $server->cpu_core_count,
            'collected_at'   => now(),
        ]);
    }
}