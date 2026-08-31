<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\MetricSnapshotData;
use Exception;

class SystemProcessSnapshotProvider implements MetricSnapshotProvider
{
    public function __construct(
        private readonly RemoteCommandService $commands
    ) {}

    public function getSnapshots(MonitoredServer $server): array
    {
        try {
            // Get full args to distinguish worker, cron, etc.
            $cmd = 'ps -eo pid,ppid,args,%cpu,%mem --sort=-%cpu | head -15';
            $result = $this->commands->execute($server, $cmd);

            if (!$result->successful || empty($result->output)) {
                return [];
            }

            return $this->parseProcesses($result->output);
        } catch (Exception $e) {
            logger()->warning("SystemProcessSnapshotProvider failed for server {$server->name}: " . $e->getMessage());
            return [];
        }
    }

    public function parseProcesses(string $output): array
    {
        $lines = explode("\n", trim($output));
        if (count($lines) <= 1) {
            return [];
        }

        // Remove header line
        array_shift($lines);

        $snapshotAt = now();
        $snapshots = [];

        // We want to aggregate CPU usage per process category to store in metric_histories
        $categories = [
            'web_server' => 0.0,
            'database' => 0.0,
            'cron_scheduler' => 0.0,
            'automation_queue' => 0.0,
            'system_other' => 0.0,
        ];

        // Also track top process by name and CPU
        $topProcessComm = 'unknown';
        $topProcessCpu = 0.0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse columns: pid, ppid, args..., %cpu, %mem
            // Since args can contain spaces, we parse from right to left or using regex
            // Let's use regex matching: pid ppid (args...) cpu mem
            if (preg_match('/^(\d+)\s+(\d+)\s+(.+?)\s+([0-9\.]+)\s+([0-9\.]+)$/', $line, $matches)) {
                $pid = (int) $matches[1];
                $ppid = (int) $matches[2];
                $args = $matches[3];
                $cpu = (float) $matches[4];
                $mem = (float) $matches[5];

                if ($cpu > $topProcessCpu) {
                    $topProcessCpu = $cpu;
                    // Extract command name from args
                    $topProcessComm = basename(explode(' ', $args)[0]);
                }

                $classified = false;

                // Check Automation / Queue
                if (str_contains($args, 'queue:work') || str_contains($args, 'queue:listen') || str_contains($args, 'horizon')) {
                    $categories['automation_queue'] += $cpu;
                    $classified = true;
                }
                // Check Cron / Scheduler
                elseif (str_contains($args, 'schedule:run') || str_contains($args, 'cron') || str_contains($args, 'crond')) {
                    $categories['cron_scheduler'] += $cpu;
                    $classified = true;
                }
                // Check Database
                elseif (str_contains($args, 'postgres') || str_contains($args, 'postmaster')) {
                    $categories['database'] += $cpu;
                    $classified = true;
                }
                // Check Web Server
                elseif (str_contains($args, 'nginx') || str_contains($args, 'apache2') || str_contains($args, 'httpd') || str_contains($args, 'php-fpm')) {
                    $categories['web_server'] += $cpu;
                    $classified = true;
                }

                if (!$classified) {
                    $categories['system_other'] += $cpu;
                }
            }
        }

        // Store category CPU metrics
        foreach ($categories as $categoryName => $cpuSum) {
            $snapshots[] = new MetricSnapshotData(
                category: 'process_category_cpu',
                metricName: $categoryName,
                metricValue: $cpuSum,
                metricUnit: '%',
                snapshotAt: $snapshotAt
            );
        }

        // Store top process info
        $snapshots[] = new MetricSnapshotData(
            category: 'process_top',
            metricName: 'cpu_percent',
            metricValue: $topProcessCpu,
            metricUnit: '%',
            snapshotAt: $snapshotAt
        );

        // Store top process name in a special way: we can hash or use a metric value encoding, 
        // or wait, we can store it in the unit field or category, but wait, the database schema allows storing name in metric_name!
        // So we can do: metricName = "top_process_name:{$topProcessComm}"
        $snapshots[] = new MetricSnapshotData(
            category: 'process_top',
            metricName: 'process_name:' . $topProcessComm,
            metricValue: 1.0,
            metricUnit: 'active',
            snapshotAt: $snapshotAt
        );

        return $snapshots;
    }
}
