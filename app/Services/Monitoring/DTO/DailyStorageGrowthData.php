<?php

namespace App\Services\Monitoring\DTO;

class DailyStorageGrowthData
{
    public function __construct(
        public readonly string $date,
        public readonly string $dateFormatted,
        public readonly int $usedBytes,
        public readonly int $growthBytes,
        public readonly string $growthFormatted,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'dateFormatted' => $this->dateFormatted,
            'usedBytes' => $this->usedBytes,
            'growthBytes' => $this->growthBytes,
            'growthFormatted' => $this->growthFormatted,
        ];
    }
}
