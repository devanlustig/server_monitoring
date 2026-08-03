<?php

namespace App\Domain\Monitoring\Connections;

use App\Domain\Monitoring\Data\ConnectionTestResult;
use App\Domain\Monitoring\Data\RemoteCommandResult;
use App\Models\MonitoredServer;
use App\Domain\Monitoring\Data\BatchCommandResult;

interface ServerConnection
{
    public function test(MonitoredServer $server): ConnectionTestResult;

    public function execute(MonitoredServer $server, string $command): RemoteCommandResult;

    public function executeMany(MonitoredServer $server,array $commands): BatchCommandResult;
}
