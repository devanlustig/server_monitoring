<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\Monitoring\MonitoringRunner;
use Illuminate\Console\Command;

class MonitorRunCommand extends Command
{
    protected $signature = 'monitor:run';

    protected $description = 'Run all monitoring collectors';

    public function handle(
        MonitoringRunner $runner
    ): int {

        $servers = MonitoredServer::where(
            'is_active',
            true
        )->get();

        if ($servers->isEmpty()) {

            $this->warn(
                'No active servers.'
            );

            return self::SUCCESS;
        }

        $this->info(
            "Monitoring {$servers->count()} server(s)..."
        );

        foreach ($servers as $server) {

            try {

                $runner->run($server);

                $this->line(
                    "✓ {$server->name}"
                );

            } catch (\Throwable $e) {

                $this->error(
                    "✗ {$server->name}"
                );

                $this->line(
                    $e->getMessage()
                );

            }

        }

        $this->newLine();

        $this->info(
            'Monitoring completed.'
        );

        return self::SUCCESS;
    }
}