<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\DailyStorageGrowthData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        // 1. Assign row numbers partitioned by date, ordered by latest snapshot first (with id DESC as tie-breaker)
        $rankedSnapshots = DB::table('disk_metrics')
            ->where('server_id', $server->id)
            ->whereNotNull('used')
            ->selectRaw("DATE(collected_at) as date, used, collected_at, ROW_NUMBER() OVER (PARTITION BY server_id, DATE(collected_at) ORDER BY collected_at DESC, id DESC) as rn");

        // 2. Select only the latest snapshot (rn = 1) for each date
        $dailyMetrics = DB::query()
            ->fromSub($rankedSnapshots, 'latest')
            ->where('rn', 1)
            ->orderBy('date', 'desc')
            ->limit($days + 1)
            ->get();

        if ($dailyMetrics->isEmpty()) {
            return [];
        }

        $result = [];
        $targetCount = min($days, count($dailyMetrics));

        for ($i = 0; $i < $targetCount; $i++) {
            $currentMetric = $dailyMetrics[$i];
            $currentDate = (string) $currentMetric->date;
            $currentUsed = (float) $currentMetric->used;

            if (isset($dailyMetrics[$i + 1])) {
                $prevMetric = $dailyMetrics[$i + 1];
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
