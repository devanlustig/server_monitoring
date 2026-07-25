<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\MemoryMetricData;
use RuntimeException;

class MemoryParser
{
    public function parse(string $output): MemoryMetricData
    {
        $lines = preg_split('/\r?\n/', trim($output));

        foreach ($lines as $line) {

            if (str_starts_with($line, 'Mem:')) {

                $values = preg_split('/\s+/', trim($line));

                return new MemoryMetricData(
                    total: (int)$values[1],
                    used: (int)$values[2],
                    free: (int)$values[3],
                    shared: (int)$values[4],
                    cache: (int)$values[5],
                    available: (int)$values[6],
                    usagePercent: round(($values[2] / $values[1]) * 100, 2),
                );
            }
        }

        throw new RuntimeException('Unable to parse memory information.');
    }
}