<?php

namespace App\Services\Monitoring;

use App\Domain\Monitoring\Data\RemoteCommandResult;
use App\Domain\Monitoring\Data\BatchCommandResult;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Connections\ServerConnectionFactory;


class RemoteCommandService
{
    public function __construct(private readonly ServerConnectionFactory $connections) {}

    public function execute(MonitoredServer $server, string $command, int $timeoutSeconds = 15): RemoteCommandResult
    {
        return $this->connections->for($server)->execute($server, $command, $timeoutSeconds);
    }

    public function executeMany(MonitoredServer $server, array $commands, int $timeoutSeconds = 15): BatchCommandResult
    {
        return $this->connections->for($server)->executeMany($server, $commands, $timeoutSeconds);
    }

    public function disconnect(MonitoredServer $server): void
    {
        $this->connections->for($server)->disconnect();
    }
}
