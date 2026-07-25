<?php

namespace App\Services\Monitoring\Parsers;

use App\Services\Monitoring\DTO\CpuMetricData;
use RuntimeException;

class CpuParser
{
    public function parse(string $output): CpuMetricData
    {
        $lines = array_values(array_filter(
            preg_split('/\R/', trim($output))
        ));

        if (count($lines) < 3) {
            throw new RuntimeException('Invalid CPU command output.');
        }

        $firstStat = $this->parseStat($lines[0]);
        $secondStat = $this->parseStat($lines[1]);

        $usage = $this->calculateUsage($firstStat, $secondStat);

        $load = preg_split('/\s+/', trim($lines[2]));

        return new CpuMetricData(
            usagePercent: $usage,
            load1: isset($load[0]) ? (float) $load[0] : null,
            load5: isset($load[1]) ? (float) $load[1] : null,
            load15: isset($load[2]) ? (float) $load[2] : null,
        );
    }

    private function parseStat(string $line): array
    {
        $parts = preg_split('/\s+/', trim($line));

        if (($parts[0] ?? '') !== 'cpu') {
            throw new RuntimeException('Invalid /proc/stat output.');
        }

        array_shift($parts);

        return array_map('intval', $parts);
    }

    private function calculateUsage(array $old, array $new): float
    {
        $oldIdle = ($old[3] ?? 0) + ($old[4] ?? 0);
        $newIdle = ($new[3] ?? 0) + ($new[4] ?? 0);

        $oldTotal = array_sum($old);
        $newTotal = array_sum($new);

        $totalDelta = $newTotal - $oldTotal;
        $idleDelta = $newIdle - $oldIdle;

        if ($totalDelta <= 0) {
            return 0;
        }

        return round(
            (($totalDelta - $idleDelta) / $totalDelta) * 100,
            2
        );
    }
}