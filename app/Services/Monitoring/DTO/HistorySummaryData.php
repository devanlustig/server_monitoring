<?php

namespace App\Services\Monitoring\DTO;

class HistorySummaryData
{
    public function __construct(
        public readonly float $current,
        public readonly float $average,
        public readonly float $maximum,
        public readonly float $minimum,
        public readonly ?float $trendPercent,
        public readonly ?float $difference,
    ) {}
}