<?php

namespace App\Services\Monitoring;

use App\Domain\Monitoring\Data\RemoteCommandResult;
use App\Domain\Monitoring\Data\BatchCommandResult;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Connections\ServerConnectionFactory;


class RemoteCommandService
{
    public function __construct(private readonly ServerConnectionFactory $connections) {}

    public function execute(MonitoredServer $server, string $command): RemoteCommandResult
    {
        return $this->connections->for($server)->execute($server, $command);
    }

    public function executeMany(MonitoredServer $server,array $commands): BatchCommandResult
    {
        return $this->connections->for($server)->executeMany($server, $commands);
    }
}
