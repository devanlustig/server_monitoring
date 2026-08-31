<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Models\CpuMetric;
use App\Models\MetricHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CpuCorrelationEngine
{
    public function analyze(MonitoredServer $server, string $timestamp): array
    {
        $targetTime = Carbon::parse($timestamp);
        $windowStart = $targetTime->copy()->subMinutes(2);
        $windowEnd = $targetTime->copy()->addMinutes(2);

        // 1. Get CPU usage closest to the target timestamp
        $query1 = CpuMetric::where('server_id', $server->id)
            ->whereBetween('collected_at', [$windowStart, $windowEnd]);
        if (DB::getDriverName() === 'sqlite') {
            $query1->orderByRaw('ABS(strftime(\'%s\', collected_at) - strftime(\'%s\', ?))', [$targetTime->toDateTimeString()]);
        } else {
            $query1->orderByRaw('ABS(EXTRACT(EPOCH FROM (collected_at - ?)))', [$targetTime->toDateTimeString()]);
        }
        $cpuMetric = $query1->first();

        // If no direct metric inside 2m window, try 10m window
        if (!$cpuMetric) {
            $query2 = CpuMetric::where('server_id', $server->id)
                ->whereBetween('collected_at', [$targetTime->copy()->subMinutes(10), $targetTime->copy()->addMinutes(10)]);
            if (DB::getDriverName() === 'sqlite') {
                $query2->orderByRaw('ABS(strftime(\'%s\', collected_at) - strftime(\'%s\', ?))', [$targetTime->toDateTimeString()]);
            } else {
                $query2->orderByRaw('ABS(EXTRACT(EPOCH FROM (collected_at - ?)))', [$targetTime->toDateTimeString()]);
            }
            $cpuMetric = $query2->first();
        }

        $cpuValue = $cpuMetric ? (float) $cpuMetric->usage_percent : 0.0;
        $actualTime = $cpuMetric ? $cpuMetric->collected_at->toDateTimeString() : $timestamp;

        // 2. Fetch historical snapshots in the window
        $snapshots = MetricHistory::where('monitored_server_id', $server->id)
            ->whereBetween('snapshot_at', [$windowStart, $windowEnd])
            ->get();

        // 3. Fetch 24h baseline data
        $baselineStart = $targetTime->copy()->subHours(24);
        $baselines = MetricHistory::where('monitored_server_id', $server->id)
            ->whereBetween('snapshot_at', [$baselineStart, $targetTime])
            ->select('category', 'metric_name', DB::raw('AVG(metric_value) as avg_value'), DB::raw('COUNT(*) as count'))
            ->groupBy('category', 'metric_name')
            ->get()
            ->keyBy(fn($item) => "{$item->category}:{$item->metric_name}");

        $insufficientBaseline = $baselines->isEmpty() || $baselines->first()->count < 5;

        // 4. Extract current values in the window
        $webCategory = $server->web_server === 'nginx' ? 'nginx' : 'apache';
        
        $currentReqsMin = (float) ($snapshots->where('category', $webCategory)->where('metric_name', 'requests_per_minute')->first()?->metric_value ?? 0.0);
        $currentTotalReqs = (float) ($snapshots->where('category', $webCategory)->where('metric_name', 'total_requests')->first()?->metric_value ?? 0.0);
        
        $currentDbActive = (float) ($snapshots->where('category', 'postgresql')->where('metric_name', 'active_connections')->first()?->metric_value ?? 0.0);
        $currentDbQueries = (float) ($snapshots->where('category', 'postgresql')->where('metric_name', 'active_queries')->first()?->metric_value ?? 0.0);

        $processCpuWeb = (float) ($snapshots->where('category', 'process_category_cpu')->where('metric_name', 'web_server')->first()?->metric_value ?? 0.0);
        $processCpuDb = (float) ($snapshots->where('category', 'process_category_cpu')->where('metric_name', 'database')->first()?->metric_value ?? 0.0);
        $processCpuCron = (float) ($snapshots->where('category', 'process_category_cpu')->where('metric_name', 'cron_scheduler')->first()?->metric_value ?? 0.0);
        $processCpuQueue = (float) ($snapshots->where('category', 'process_category_cpu')->where('metric_name', 'automation_queue')->first()?->metric_value ?? 0.0);
        $processCpuOther = (float) ($snapshots->where('category', 'process_category_cpu')->where('metric_name', 'system_other')->first()?->metric_value ?? 0.0);

        // Find top process info
        $topProcessCpu = (float) ($snapshots->where('category', 'process_top')->where('metric_name', 'cpu_percent')->first()?->metric_value ?? 0.0);
        $topProcessNameRecord = $snapshots->where('category', 'process_top')->filter(fn($s) => str_starts_with($s->metric_name, 'process_name:'))->first();
        $topProcessName = $topProcessNameRecord ? str_replace('process_name:', '', $topProcessNameRecord->metric_name) : 'unknown';

        // 5. Get Baselines
        $baseReqsMin = (float) ($baselines->get("{$webCategory}:requests_per_minute")?->avg_value ?? 10.0);
        $baseDbActive = (float) ($baselines->get("postgresql:active_connections")?->avg_value ?? 2.0);
        
        $baseCpuWeb = (float) ($baselines->get("process_category_cpu:web_server")?->avg_value ?? 5.0);
        $baseCpuDb = (float) ($baselines->get("process_category_cpu:database")?->avg_value ?? 5.0);
        $baseCpuCron = (float) ($baselines->get("process_category_cpu:cron_scheduler")?->avg_value ?? 2.0);
        $baseCpuQueue = (float) ($baselines->get("process_category_cpu:automation_queue")?->avg_value ?? 2.0);
        $baseCpuOther = (float) ($baselines->get("process_category_cpu:system_other")?->avg_value ?? 5.0);

        // 6. Scoring Calculations
        // A. Application Request Correlation
        $reqIncrease = $baseReqsMin > 0 ? (($currentReqsMin - $baseReqsMin) / $baseReqsMin) * 100 : 0;
        $appScore = 0.0;
        if ($cpuValue > 10.0) {
            $appScore += $processCpuWeb * 0.8;
            if ($reqIncrease > 20) {
                $appScore += min(30, $reqIncrease * 0.3);
            }
        }
        $appScore = max(0.0, min(100.0, $appScore));

        // B. Database Correlation
        $dbIncrease = $baseDbActive > 0 ? (($currentDbActive - $baseDbActive) / $baseDbActive) * 100 : 0;
        $dbScore = 0.0;
        if ($cpuValue > 10.0) {
            $dbScore += $processCpuDb * 0.9;
            if ($dbIncrease > 20) {
                $dbScore += min(20, $dbIncrease * 0.2);
            }
        }
        $dbScore = max(0.0, min(100.0, $dbScore));

        // C. Cron / Scheduler Correlation
        $cronScore = 0.0;
        if ($cpuValue > 10.0 && $processCpuCron > 2.0) {
            $cronScore = $processCpuCron * 1.0 + 20;
        }
        $cronScore = max(0.0, min(100.0, $cronScore));

        // D. Automation / Queue Correlation
        $queueScore = 0.0;
        if ($cpuValue > 10.0 && $processCpuQueue > 2.0) {
            $queueScore = $processCpuQueue * 1.0 + 20;
        }
        $queueScore = max(0.0, min(100.0, $queueScore));

        // E. Web Server process specific CPU
        $webServerScore = min(100.0, $processCpuWeb * 1.1);

        // F. System Process
        $systemScore = 0.0;
        if ($cpuValue > 10.0) {
            // High process CPU not classified in above categories
            $systemScore = $processCpuOther * 1.0;
            if ($appScore < 40 && $dbScore < 40 && $cronScore < 40 && $queueScore < 40 && $topProcessCpu > 50) {
                $systemScore = max($systemScore, $topProcessCpu);
            }
        }
        $systemScore = max(0.0, min(100.0, $systemScore));

        // Determine likely cause
        $scores = [
            'Application Request' => $appScore,
            'Database' => $dbScore,
            'Cron/Scheduler' => $cronScore,
            'Automation/Queue' => $queueScore,
            'System Process' => $systemScore
        ];
        arsort($scores);
        $primaryCause = key($scores);
        $highestScore = current($scores);

        $confidence = 'No Evidence';
        if ($highestScore >= 80) $confidence = 'Strong Correlation';
        elseif ($highestScore >= 60) $confidence = 'Likely Cause';
        elseif ($highestScore >= 30) $confidence = 'Contributing Factor';

        // Evidence preparation
        $evidence = [];
        if ($appScore >= 30) {
            $evidence[] = "Application request rate is " . number_format($currentReqsMin, 1) . " req/min (baseline: " . number_format($baseReqsMin, 1) . " req/min, +" . number_format($reqIncrease, 1) . "%).";
            $evidence[] = "Web/PHP processes consumed " . number_format($processCpuWeb, 1) . "% CPU.";
        }
        if ($dbScore >= 30) {
            $evidence[] = "Database active connections/queries: {$currentDbActive} active (baseline: " . number_format($baseDbActive, 1) . ", +" . number_format($dbIncrease, 1) . "%).";
            $evidence[] = "Postgres processes consumed " . number_format($processCpuDb, 1) . "% CPU.";
        }
        if ($cronScore >= 30) {
            $evidence[] = "Active Cron/Scheduler tasks detected, consuming " . number_format($processCpuCron, 1) . "% CPU.";
        }
        if ($queueScore >= 30) {
            $evidence[] = "Active Queue workers/Horizon workers detected, consuming " . number_format($processCpuQueue, 1) . "% CPU.";
        }
        if ($topProcessCpu > 10.0) {
            $evidence[] = "Process '{$topProcessName}' was the highest single consumer at " . number_format($topProcessCpu, 1) . "% CPU.";
        }

        if (empty($evidence)) {
            $evidence[] = "No significant anomalous activity detected in application requests, database queries, or automated workers.";
        }

        return [
            'timestamp' => $actualTime,
            'cpu' => $cpuValue,
            'insufficient_baseline' => $insufficientBaseline,
            'summary' => [
                'primaryCause' => $highestScore >= 30 ? $primaryCause : 'None / Idle',
                'confidence' => $confidence,
                'evidence' => $evidence,
            ],
            'nodes' => [
                [
                    'id' => 'cpu',
                    'name' => 'CPU Usage',
                    'value' => number_format($cpuValue, 1) . '%',
                    'score' => $cpuValue,
                    'status' => $cpuValue > 80 ? 'danger' : ($cpuValue > 50 ? 'warning' : 'success'),
                    'evidence' => "Total CPU usage is at " . number_format($cpuValue, 1) . "%"
                ],
                [
                    'id' => 'app',
                    'name' => 'Application Request',
                    'value' => number_format($currentReqsMin, 1) . ' req/min',
                    'score' => $appScore,
                    'status' => $appScore >= 80 ? 'danger' : ($appScore >= 60 ? 'warning' : ($appScore >= 30 ? 'info' : 'success')),
                    'evidence' => "Req rate: " . number_format($currentReqsMin, 1) . " (baseline: " . number_format($baseReqsMin, 1) . ")"
                ],
                [
                    'id' => 'db',
                    'name' => 'PostgreSQL Database',
                    'value' => $currentDbQueries . ' active queries',
                    'score' => $dbScore,
                    'status' => $dbScore >= 80 ? 'danger' : ($dbScore >= 60 ? 'warning' : ($dbScore >= 30 ? 'info' : 'success')),
                    'evidence' => "Active Queries: {$currentDbQueries}, connections: {$currentDbActive}"
                ],
                [
                    'id' => 'cron',
                    'name' => 'Cron / Scheduler',
                    'value' => number_format($processCpuCron, 1) . '% CPU',
                    'score' => $cronScore,
                    'status' => $cronScore >= 80 ? 'danger' : ($cronScore >= 60 ? 'warning' : ($cronScore >= 30 ? 'info' : 'success')),
                    'evidence' => "Cron process CPU load: " . number_format($processCpuCron, 1) . "%"
                ],
                [
                    'id' => 'queue',
                    'name' => 'Automation / Queue',
                    'value' => number_format($processCpuQueue, 1) . '% CPU',
                    'score' => $queueScore,
                    'status' => $queueScore >= 80 ? 'danger' : ($queueScore >= 60 ? 'warning' : ($queueScore >= 30 ? 'info' : 'success')),
                    'evidence' => "Queue workers CPU load: " . number_format($processCpuQueue, 1) . "%"
                ],
                [
                    'id' => 'system',
                    'name' => 'System / Other Processes',
                    'value' => "Top: {$topProcessName} (" . number_format($topProcessCpu, 1) . "%)",
                    'score' => $systemScore,
                    'status' => $systemScore >= 80 ? 'danger' : ($systemScore >= 60 ? 'warning' : ($systemScore >= 30 ? 'info' : 'success')),
                    'evidence' => "Unclassified process load: " . number_format($processCpuOther, 1) . "%"
                ]
            ]
        ];
    }
}
