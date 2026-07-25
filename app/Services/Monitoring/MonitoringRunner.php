<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\CpuCollector;
use App\Services\Monitoring\Collectors\MemoryCollector;
use App\Services\Monitoring\Collectors\DiskCollector;
use App\Services\Monitoring\Recorders\CpuMetricRecorder;
use App\Services\Monitoring\Recorders\MemoryMetricRecorder;
use App\Services\Monitoring\Recorders\DiskMetricRecorder;

class MonitoringRunner
{
    public function __construct(
        private readonly CpuCollector $cpuCollector,
        private readonly MemoryCollector $memoryCollector,
        private readonly DiskCollector $diskCollector,

        private readonly CpuMetricRecorder $cpuRecorder,
        private readonly MemoryMetricRecorder $memoryRecorder,
        private readonly DiskMetricRecorder $diskRecorder,

        private readonly ServerStatusService $status,
    ) {
    }

    public function run(MonitoredServer $server): void
    {
        try {

            // CPU
            $cpu = $this->cpuCollector->collect($server);
            $this->cpuRecorder->record($server, $cpu);

            // Memory
            $memory = $this->memoryCollector->collect($server);
            $this->memoryRecorder->record($server, $memory);

            // Disk
            $disk = $this->diskCollector->collect($server);
            $this->diskRecorder->record($server, $disk);

            // Server Online
            $this->status->online($server);

        } catch (\Throwable $e) {

            $this->status->offline(
                $server,
                $e->getMessage()
            );

            throw $e;
        }
    }
}