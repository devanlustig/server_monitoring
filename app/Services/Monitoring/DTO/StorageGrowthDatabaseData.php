<?php

namespace App\Services\Monitoring\DTO;

class StorageGrowthDatabaseData
{
    public function __construct(
        public readonly string $databaseOid,
        public readonly string $databaseName,
        public readonly int $currentSizeBytes,
        public readonly ?int $previousSizeBytes,
        public readonly ?int $growthBytes,
        public readonly string $currentSizeFormatted,
        public readonly string $previousSizeFormatted,
        public readonly string $growthFormatted,
    ) {}

    public function toArray(): array
    {
        return [
            'databaseOid' => $this->databaseOid,
            'databaseName' => $this->databaseName,
            'currentSizeBytes' => $this->currentSizeBytes,
            'previousSizeBytes' => $this->previousSizeBytes,
            'growthBytes' => $this->growthBytes,
            'currentSizeFormatted' => $this->currentSizeFormatted,
            'previousSizeFormatted' => $this->previousSizeFormatted,
            'growthFormatted' => $this->growthFormatted,
        ];
    }
}
