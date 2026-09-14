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
        // 1. Group by calendar date in database and get max(collected_at) per date
        $latestPerDateSub = DB::table('disk_metrics')
            ->where('server_id', $server->id)
            ->whereNotNull('used')
            ->selectRaw("DATE(collected_at) as snapshot_date, MAX(collected_at) as max_collected_at")
            ->groupByRaw("DATE(collected_at)");

        // 2. Join back to get the latest snapshot row per date, limit to ($days + 1)
        $dailyMetrics = DB::table('disk_metrics as m')
            ->joinSub($latestPerDateSub, 'latest', function ($join) use ($server) {
                $join->on('m.collected_at', '=', 'latest.max_collected_at')
                     ->where('m.server_id', '=', $server->id);
            })
            ->where('m.server_id', $server->id)
            ->whereNotNull('m.used')
            ->select(['latest.snapshot_date as date', 'm.used', 'm.collected_at'])
            ->orderBy('m.collected_at', 'desc')
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
