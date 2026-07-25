<?php

namespace App\Services\Monitoring\DTO;

class DiskMetricData
{
    public function __construct(

        public readonly int $total,

        public readonly int $used,

        public readonly int $available,

        public readonly float $usagePercent,

    ){}
}