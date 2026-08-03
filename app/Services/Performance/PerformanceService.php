<?php

namespace App\Services\Performance;

use App\Models\ApplicationRequestLog;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    public function summary(): array
    {
        $query = ApplicationRequestLog::query();

        $summary = $query
            ->selectRaw('
                COUNT(*) as total_requests,
                AVG(response_time_ms) as average_response_time,
                MIN(response_time_ms) as min_response_time,
                MAX(response_time_ms) as max_response_time,
                SUM(CASE WHEN is_slow THEN 1 ELSE 0 END) as slow_requests,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_requests
            ')
            ->first();

        return [

            'total_requests' => (int) $summary->total_requests,

            'average_response_time' => round(
                (float) $summary->average_response_time,
                2
            ),

            'min_response_time' => round(
                (float) $summary->min_response_time,
                2
            ),

            'max_response_time' => round(
                (float) $summary->max_response_time,
                2
            ),

            'slow_requests' => (int) $summary->slow_requests,

            'error_requests' => (int) $summary->error_requests,

            'error_rate' => $summary->total_requests > 0
                ? round(
                    ($summary->error_requests / $summary->total_requests) * 100,
                    2
                )
                : 0,

        ];
    }

    public function slowEndpoints(int $limit = 10)
    {
        return ApplicationRequestLog::query()
            ->select(
                'route_name',
                'controller',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('AVG(response_time_ms) as average_response_time'),
                DB::raw('MIN(response_time_ms) as min_response_time'),
                DB::raw('MAX(response_time_ms) as max_response_time'),
                DB::raw('SUM(CASE WHEN is_slow THEN 1 ELSE 0 END) as slow_requests')
            )
            ->whereNotNull('route_name')
            ->groupBy(
                'route_name',
                'controller'
            )
            ->orderByDesc('average_response_time')
            ->limit($limit)
            ->get();
    }


    public function endpointPerformance()
    {
        return ApplicationRequestLog::query()
            ->select(
                'route_name',
                'controller',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('AVG(response_time_ms) as average_response_time'),
                DB::raw('MIN(response_time_ms) as min_response_time'),
                DB::raw('MAX(response_time_ms) as max_response_time'),
                DB::raw('SUM(CASE WHEN is_slow THEN 1 ELSE 0 END) as slow_requests')
            )

            ->whereNotNull('route_name')
            ->groupBy(
                'route_name',
                'controller'
            )
            ->orderByDesc('total_requests')
            ->get();
    }

    public function recentRequests(int $limit = 20)
    {
        return ApplicationRequestLog::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function responseTrend(int $minutes = 60)
    {
        return ApplicationRequestLog::query()
            ->where(
                'created_at',
                '>=',
                now()->subMinutes($minutes)
            )
            ->select(
                DB::raw("
                    DATE_TRUNC(
                        'minute',
                        created_at
                    ) as minute
                "),
                DB::raw("
                    AVG(response_time_ms)
                    as average_response_time
                ")
            )

            ->groupBy(
                DB::raw("
                    DATE_TRUNC(
                        'minute',
                        created_at
                    )
                ")
            )
            ->orderBy('minute')
            ->get();
    }
}