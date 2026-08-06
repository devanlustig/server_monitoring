<?php

namespace App\Services\Monitoring\DTO;

use DateTimeInterface;

class MetricSnapshotData
{
    public function __construct(
        public readonly string $category,
        public readonly string $metricName,
        public readonly float $metricValue,
        public readonly ?string $metricUnit,
        public readonly DateTimeInterface $snapshotAt,
    ) {}
}
