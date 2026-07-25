<?php

namespace App\Services\Monitoring\DTO;

class PostgreSqlSummaryData
{
    public function __construct(
        public readonly bool $running,
        public readonly int $currentConnections,
        public readonly int $maxConnections,
        public readonly float $usagePercent,
        public readonly int $activeConnections,
        public readonly int $idleConnections,
        public readonly int $idleInTransactionConnections,
        public readonly string $databaseSize,
    ) {
    }
}