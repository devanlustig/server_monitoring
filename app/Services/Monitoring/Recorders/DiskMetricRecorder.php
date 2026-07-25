<?php

namespace App\Services\Monitoring\Recorders;

use App\Models\DiskMetric;
use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\DiskMetricData;

class DiskMetricRecorder
{
    public function record(

        MonitoredServer $server,

        DiskMetricData $metric,

    ): void{

        DiskMetric::create([

            'server_id'=>$server->id,

            'hostname'=>$server->system_hostname,

            'total'=>$metric->total,

            'used'=>$metric->used,

            'available'=>$metric->available,

            'usage_percent'=>$metric->usagePercent,

            'collected_at'=>now(),

        ]);

    }
}