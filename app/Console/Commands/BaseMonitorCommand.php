<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\Monitoring\ServerStatusService;

abstract class BaseMonitorCommand extends Command
{
    abstract protected function collectMetric(MonitoredServer $server): void;

    abstract protected function monitorName(): string;

    public function __construct(
    protected readonly ServerStatusService $status
    ) {
        parent::__construct();
    }


    public function handle(): int
    {
        $servers = MonitoredServer::where('is_active', true)->get();

        if ($servers->isEmpty()) {

            $this->warn('No active servers found.');

            return self::SUCCESS;
        }

        $this->info(
            "Collecting {$this->monitorName()} metrics from {$servers->count()} server(s)..."
        );

        foreach ($servers as $server) {
            try {
                $this->collectMetric($server);
                $this->status->online($server);
                $this->line(
                    sprintf(
                        '✓ %-20s [%s]',
                        $server->name,
                        $this->monitorName()
                    )
                );

            } catch (\Throwable $e) {

                $this->status->offline(
                    $server,
                    $e->getMessage()
                );

                $this->error("✗ {$server->name}");

                $this->line("<fg=red>{$e->getMessage()}</>");

                report($e);
            }
        }

        $this->newLine();

        $this->info("{$this->monitorName()} monitoring completed.");

        return self::SUCCESS;
    }
}