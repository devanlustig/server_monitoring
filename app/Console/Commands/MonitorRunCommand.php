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

    public function __construct(
        private readonly MonitoringRunner $runner
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lockFile = storage_path('framework/cache/monitor_run.lock');
        
        // Ensure directory exists
        if (!is_dir(dirname($lockFile))) {
            mkdir(dirname($lockFile), 0755, true);
        }

        $fp = fopen($lockFile, 'w+');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            $this->warn("Another instance of monitor:run is already running (PID: " . getmypid() . "). Skipping.");
            return self::SUCCESS;
        }

        $startTime = microtime(true);
        $this->info('Monitoring cycle started (PID: ' . getmypid() . ')...');
        logger()->info('Monitoring cycle started.', ['pid' => getmypid()]);

        try {
            $this->processServers();
        } finally {
            $duration = round(microtime(true) - $startTime, 2);
            $this->info("Monitoring cycle completed in {$duration}s.");
            logger()->info("Monitoring cycle completed.", ['pid' => getmypid(), 'duration_seconds' => $duration]);

            flock($fp, LOCK_UN);
            fclose($fp);
        }

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
            $serverStartTime = microtime(true);
            try {
                $this->runner->run($server);
                
                $serverDuration = round(microtime(true) - $serverStartTime, 2);
                $this->line("✓ {$server->name} ({$serverDuration}s)");
            } catch (Throwable $e) {
                $serverDuration = round(microtime(true) - $serverStartTime, 2);
                $this->error("✗ {$server->name} ({$serverDuration}s) - Failed");
                $this->line($e->getMessage());

                logger()->error("Failed monitoring server {$server->name} (ID: {$server->id}): {$e->getMessage()}", [
                    'server_id' => $server->id,
                    'duration_seconds' => $serverDuration,
                    'exception' => $e,
                ]);
            }
        }

        $this->newLine();
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