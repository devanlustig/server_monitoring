<?php

namespace App\Services\Monitoring;

use App\Models\DiskMetric;
use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\DailyStorageGrowthData;
use Carbon\Carbon;

class DiskStorageGrowthService
{
    /**
     * Get daily storage growth for a monitored server for up to the last $days calendar days with available snapshot data.
     *
     * @param MonitoredServer $server
     * @param int $days Number of daily growth records to return (default 5)
     * @return DailyStorageGrowthData[]
     */
    public function getDailyGrowth(MonitoredServer $server, int $days = 5): array
    {
        $rawMetrics = DiskMetric::where('server_id', $server->id)
            ->whereNotNull('used')
            ->orderBy('collected_at', 'desc')
            ->get();

        if ($rawMetrics->isEmpty()) {
            return [];
        }

        $dailySnapshots = [];
        foreach ($rawMetrics as $metric) {
            if (!$metric->collected_at) {
                continue;
            }
            $date = $metric->collected_at->format('Y-m-d');
            if (!isset($dailySnapshots[$date])) {
                $dailySnapshots[$date] = $metric;
            }
        }

        $dates = array_keys($dailySnapshots);
        if (empty($dates)) {
            return [];
        }

        $result = [];
        $targetCount = min($days, count($dates));

        for ($i = 0; $i < $targetCount; $i++) {
            $currentDate = $dates[$i];
            $currentMetric = $dailySnapshots[$currentDate];
            $currentUsed = (float) $currentMetric->used;

            if (isset($dates[$i + 1])) {
                $prevDate = $dates[$i + 1];
                $prevMetric = $dailySnapshots[$prevDate];
                $prevUsed = (float) $prevMetric->used;
                $growthBytes = $currentUsed - $prevUsed;
            } else {
                $growthBytes = 0.0;
            }

            $dateFormatted = Carbon::parse($currentDate)->format('d M');
            $growthFormatted = $this->formatGrowth($growthBytes);

            $result[] = new DailyStorageGrowthData(
                date: $currentDate,
                dateFormatted: $dateFormatted,
                usedBytes: (int) $currentUsed,
                growthBytes: (int) $growthBytes,
                growthFormatted: $growthFormatted
            );
        }

        return $result;
    }

    public function formatGrowth(float $bytes): string
    {
        if ($bytes == 0) {
            return '0 GB';
        }

        $absBytes = abs($bytes);
        $sign = $bytes > 0 ? '+' : '-';

        if ($absBytes >= 1073741824) {
            $val = number_format($absBytes / 1073741824, 1);
            return $sign . $val . ' GB';
        }

        if ($absBytes >= 1048576) {
            $val = number_format($absBytes / 1048576, 1);
            return $sign . $val . ' MB';
        }

        if ($absBytes >= 1024) {
            $val = number_format($absBytes / 1024, 1);
            return $sign . $val . ' KB';
        }

        return $sign . (int) $absBytes . ' B';
    }
}
