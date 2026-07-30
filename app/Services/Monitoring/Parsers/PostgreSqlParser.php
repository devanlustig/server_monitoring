<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\PostgreSqlSummaryData;
use RuntimeException;

class PostgreSqlParser
{
    public function parse(string $output): PostgreSqlSummaryData
    {
        logger()->debug(
        'RAW SUMMARY',
            [
                'output' => $output,
            ]
        );

        $output = trim($output);

        if ($output === '') {
            logger()->error(
                'PostgreSQL Summary Empty',
                [
                    'output' => $output,
                ]
            );

            return new PostgreSqlSummaryData(
                running: false,
                currentConnections: 0,
                maxConnections: 0,
                usagePercent: 0,
                activeConnections: 0,
                idleConnections: 0,
                idleInTransactionConnections: 0,
                databaseSize: '-',
            );
        }

        $parts = explode('|', $output);
        if (count($parts) !== 6) {

            logger()->error(
                'Invalid PostgreSQL summary output',
                [
                    'output' => $output,
                    'parts' => $parts,
                ]
            );
            return new PostgreSqlSummaryData(
                running: false,
                currentConnections: 0,
                maxConnections: 0,
                usagePercent: 0,
                activeConnections: 0,
                idleConnections: 0,
                idleInTransactionConnections: 0,
                databaseSize: '-',
            );
        }

        return new PostgreSqlSummaryData(
            running: true,
            currentConnections: (int) $parts[0],
            maxConnections: (int) $parts[1],
            usagePercent: (int) $parts[1] > 0
                ? round(((int) $parts[0] / (int) $parts[1]) * 100, 2)
                : 0,
            activeConnections: (int) $parts[2],
            idleConnections: (int) $parts[3],
            idleInTransactionConnections: (int) $parts[4],
            databaseSize: trim($parts[5]),
        );
    }
}