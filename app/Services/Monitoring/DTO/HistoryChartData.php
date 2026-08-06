<?php

namespace App\Services\Monitoring\DTO;

class HistoryChartData
{
    public function __construct(
        public readonly array $labels,
        public readonly array $values,
    ) {}
}