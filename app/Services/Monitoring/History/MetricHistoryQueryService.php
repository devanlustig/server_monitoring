<?php

namespace App\Services\Monitoring\History;

use App\Models\MetricHistory;
use App\Models\MonitoredServer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Services\Monitoring\DTO\HistoryChartData;
use App\Services\Monitoring\DTO\HistorySummaryData;

class MetricHistoryQueryService
{
    public function getSeries(
        MonitoredServer $server,
        string $category,
        string $metricName,
        Carbon $from,
        Carbon $to,
    ): Collection {

        return MetricHistory::query()

            ->where('monitored_server_id', $server->id)

            ->where('category', $category)

            ->where('metric_name', $metricName)

            ->whereBetween('snapshot_at', [$from, $to])

            ->orderBy('snapshot_at')

            ->get([
                'snapshot_at',
                'metric_value',
            ]);

    }

    public function latest(
        MonitoredServer $server,
        string $category,
        string $metricName,
    ): ?MetricHistory {

        return MetricHistory::query()

            ->where('monitored_server_id', $server->id)

            ->where('category', $category)

            ->where('metric_name', $metricName)

            ->latest('snapshot_at')

            ->first();

    }

    public function average(
        MonitoredServer $server,
        string $category,
        string $metricName,
        Carbon $from,
        Carbon $to,
    ): float {

        return round(

            MetricHistory::query()

                ->where('monitored_server_id', $server->id)

                ->where('category', $category)

                ->where('metric_name', $metricName)

                ->whereBetween('snapshot_at', [$from, $to])

                ->avg('metric_value') ?? 0,

            2

        );

    }

    public function maximum(
        MonitoredServer $server,
        string $category,
        string $metricName,
        Carbon $from,
        Carbon $to,
    ): float {

        return round(

            MetricHistory::query()

                ->where('monitored_server_id', $server->id)

                ->where('category', $category)

                ->where('metric_name', $metricName)

                ->whereBetween('snapshot_at', [$from, $to])

                ->max('metric_value') ?? 0,

            2

        );

    }

    public function minimum(
        MonitoredServer $server,
        string $category,
        string $metricName,
        Carbon $from,
        Carbon $to,
    ): float {

        return round(

            MetricHistory::query()

                ->where('monitored_server_id', $server->id)

                ->where('category', $category)

                ->where('metric_name', $metricName)

                ->whereBetween('snapshot_at', [$from, $to])

                ->min('metric_value') ?? 0,

            2

        );

    }

    public function chart(MonitoredServer $server,string $category,string $metricName,Carbon $from,Carbon $to,
    ): HistoryChartData
    {
        $series = $this->getSeries(
            $server,
            $category,
            $metricName,
            $from,
            $to
        );

        return new HistoryChartData(
            labels: $series
                ->pluck('snapshot_at')
                ->map(fn ($time) => $time->format('H:i'))
                ->toArray(),
            values: $series
                ->pluck('metric_value')
                ->map(fn ($value) => round($value,2))
                ->toArray(),
        );
    }

    public function chartLast24Hours(MonitoredServer $server,string $category,string $metricName,
        ): HistoryChartData {

            return $this->chart(
                $server,
                $category,
                $metricName,
                now()->subDay(),
                now(),
            );

        }

        public function chartLast7Days(MonitoredServer $server,string $category,string $metricName,
        ): HistoryChartData {

            return $this->chart(
                $server,
                $category,
                $metricName,
                now()->subDays(7),
                now(),
            );

        }

        public function chartLast30Days(MonitoredServer $server,string $category,string $metricName,
        ): HistoryChartData {

            return $this->chart(
                $server,
                $category,
                $metricName,
                now()->subDays(30),
                now(),
            );

        }

    public function summary(MonitoredServer $server,string $category,string $metricName,Carbon $from,Carbon $to,
    ): HistorySummaryData
    {
        $series = $this->getSeries(
            $server,
            $category,
            $metricName,
            $from,
            $to
        );

        if ($series->isEmpty()) {
            return new HistorySummaryData(
                current: 0,
                average: 0,
                maximum: 0,
                minimum: 0,
                trendPercent: null,
                difference: null,
            );
        }

        $values = $series->pluck('metric_value');
        $current = (float) $values->last();
        $average = round($values->avg(), 2);
        $maximum = round($values->max(), 2);
        $minimum = round($values->min(), 2);
        $first = (float) $values->first();
        $difference = round($current - $first, 2);

        $trend = null;
        if ($first != 0) {
            $trend = round(
                (($current - $first) / $first) * 100,
                2
            );
        }

        return new HistorySummaryData(
            current: $current,
            average: $average,
            maximum: $maximum,
            minimum: $minimum,
            trendPercent: $trend,
            difference: $difference,
        );
    }

    public function summaryLast24Hours(MonitoredServer $server,string $category,string $metricName,
        ): HistorySummaryData {

            return $this->summary(
                $server,
                $category,
                $metricName,
                now()->subDay(),
                now(),
            );

        }

        public function summaryLast7Days(MonitoredServer $server,string $category,string $metricName,
        ): HistorySummaryData {

            return $this->summary(
                $server,
                $category,
                $metricName,
                now()->subDays(7),
                now(),
            );

        }

        public function summaryLast30Days(MonitoredServer $server,string $category,string $metricName,
        ): HistorySummaryData {

            return $this->summary(
                $server,
                $category,
                $metricName,
                now()->subDays(30),
                now(),
            );

        }

        public function resolvePeriod(string $period): array
        {
            return match ($period) {

                '1h' => [
                    now()->subHour(),
                    now(),
                ],

                '6h' => [
                    now()->subHours(6),
                    now(),
                ],

                '24h' => [
                    now()->subDay(),
                    now(),
                ],

                '7d' => [
                    now()->subDays(7),
                    now(),
                ],

                '30d' => [
                    now()->subDays(30),
                    now(),
                ],

                default => [
                    now()->subDay(),
                    now(),
                ],
            };
        }
}