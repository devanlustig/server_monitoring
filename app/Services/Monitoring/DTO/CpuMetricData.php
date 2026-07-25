<?php

namespace App\Services\Monitoring\DTO;

class CpuMetricData
{
    public function __construct(
        public readonly ?float $usagePercent,
        public readonly ?float $load1,
        public readonly ?float $load5,
        public readonly ?float $load15,
    ) {}
}