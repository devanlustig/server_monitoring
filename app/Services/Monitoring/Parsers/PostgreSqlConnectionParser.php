<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\PostgreSqlConnectionData;

class PostgreSqlConnectionParser
{
    public function parse(string $output): array
    {
        $rows = [];

        foreach (preg_split('/\R/', trim($output)) as $line) {

            if (
                blank($line) ||
                str_contains($line, 'Permission denied')
            ) {
                continue;
            }

            $parts = explode('|', $line);

            if (count($parts) < 11) {
                continue;
            }

            $rows[] = new PostgreSqlConnectionData(

                pid: (int) $parts[0],
                database: $parts[1],
                user: $parts[2],
                application: $parts[3],
                client: $parts[4] ?: 'Local Socket',
                backendType: 'client backend',
                state: $parts[5],
                waitEventType: '',
                waitEvent: '',
                backendStart: $parts[6],
                stateChange: $parts[7],
                connectionAge: $parts[8],
                activityDuration: $parts[9],
                query: $parts[10],

            );
        }

        return $rows;
    }
}