<?php

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\Monitoring\MonitoringRunner;
use Illuminate\Console\Command;
use Throwable;

class MonitorRunCommand extends Command
{
    protected $signature = 'monitor:run';

    protected $description = 'Run all monitoring collectors as a continuous daemon process';

    private bool $running = true;

    public function __construct(
        private readonly MonitoringRunner $runner
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->registerSignalHandlers();

        $this->info('Monitoring daemon started...');

        while ($this->running) {
            $this->processServers();

            if (!$this->running) {
                break;
            }

            $this->sleep(30);
        }

        $this->info('Monitoring daemon stopped...');

        return self::SUCCESS;
    }

    private function processServers(): void
    {
        $servers = MonitoredServer::where('is_active', true)->get();

        if ($servers->isEmpty()) {
            $this->warn('No active servers.');
            return;
        }

        $this->info("Monitoring {$servers->count()} server(s)...");

        foreach ($servers as $server) {
            if (!$this->running) {
                break;
            }

            try {
                $this->runner->run($server);

                $this->line("✓ {$server->name}");
            } catch (Throwable $e) {
                $this->error("✗ {$server->name}");
                $this->line($e->getMessage());

                logger()->error("Failed monitoring server {$server->name} (ID: {$server->id}): {$e->getMessage()}", [
                    'server_id' => $server->id,
                    'exception' => $e,
                ]);
            }
        }

        $this->newLine();
        $this->info('Monitoring iteration completed.');
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $signalHandler = function (int $signal): void {
            $this->running = false;
        };

        pcntl_signal(SIGTERM, $signalHandler);
        pcntl_signal(SIGINT, $signalHandler);
    }

    private function sleep(int $seconds): void
    {
        for ($i = 0; $i < $seconds; $i++) {
            if (!$this->running) {
                break;
            }
            sleep(1);
        }
    }
}