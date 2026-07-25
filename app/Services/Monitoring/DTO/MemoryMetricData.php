<?php

namespace App\Services\Monitoring\DTO;

class MemoryMetricData
{
    public function __construct(
        public readonly int $total,
        public readonly int $used,
        public readonly int $free,
        public readonly int $shared,
        public readonly int $cache,
        public readonly int $available,
        public readonly float $usagePercent,
    ) {}
}