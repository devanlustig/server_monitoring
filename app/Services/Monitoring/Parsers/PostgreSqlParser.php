<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\PostgreSqlSummaryData;
use RuntimeException;

class PostgreSqlParser
{
    public function parse(string $output): PostgreSqlSummaryData
    {
        $lines = array_filter(
            preg_split('/\R/', trim($output))
        );
        // Ambil baris terakhir (hasil psql)
        $output = trim(end($lines));

        if ($output === '') {
            throw new RuntimeException(
                'Empty PostgreSQL response.'
            );
        }

        $parts = explode('|', $output);

        if (count($parts) !== 6) {
            throw new RuntimeException(
                'Invalid PostgreSQL summary output.'
            );
        }

        $current = (int) $parts[0];
        $max = (int) $parts[1];
        $active = (int) $parts[2];
        $idle = (int) $parts[3];
        $idleTransaction = (int) $parts[4];

        return new PostgreSqlSummaryData(
            running: true,
            currentConnections: $current,
            maxConnections: $max,
            usagePercent: round(($current / max($max, 1)) * 100, 2),
            activeConnections: $active,
            idleConnections: $idle,
            idleInTransactionConnections: $idleTransaction,
            databaseSize: trim($parts[5]),
        );
    }
}