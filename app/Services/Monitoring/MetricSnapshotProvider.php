<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\MetricSnapshotData;

interface MetricSnapshotProvider
{
    /**
     * @param MonitoredServer $server
     * @return MetricSnapshotData[]
     */
    public function getSnapshots(MonitoredServer $server): array;
}
