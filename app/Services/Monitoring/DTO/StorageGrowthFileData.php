<?php

namespace App\Services\Monitoring\DTO;

class StorageGrowthFileData
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly int $currentSizeBytes,
        public readonly ?int $previousSizeBytes,
        public readonly ?int $growthBytes,
        public readonly string $currentSizeFormatted,
        public readonly string $previousSizeFormatted,
        public readonly string $growthFormatted,
        public readonly ?string $databaseName = null,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'filename' => $this->filename,
            'currentSizeBytes' => $this->currentSizeBytes,
            'previousSizeBytes' => $this->previousSizeBytes,
            'growthBytes' => $this->growthBytes,
            'currentSizeFormatted' => $this->currentSizeFormatted,
            'previousSizeFormatted' => $this->previousSizeFormatted,
            'growthFormatted' => $this->growthFormatted,
            'database_name' => $this->databaseName,
        ];
    }
}
