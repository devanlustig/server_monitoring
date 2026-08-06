<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitoringSnapshotRunner;
use Illuminate\Console\Command;

class MonitoringSnapshotCommand extends Command
{
    protected $signature = 'monitor:snapshot';
    protected $description = 'Take monitoring metric snapshots';
    public function __construct(
        private readonly MonitoringSnapshotRunner $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Monitoring Snapshot Started');
        $this->runner->run();
        $this->info('Monitoring Snapshot Finished');
        return self::SUCCESS;
    }
}