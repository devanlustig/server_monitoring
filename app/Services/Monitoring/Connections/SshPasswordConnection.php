<?php

namespace App\Services\Monitoring\Connections;

use App\Domain\Monitoring\Connections\ServerConnection;
use App\Domain\Monitoring\Data\ConnectionTestResult;
use App\Domain\Monitoring\Data\RemoteCommandResult;
use App\Models\MonitoredServer;
use phpseclib3\Net\SSH2;
use Throwable;

class SshPasswordConnection implements ServerConnection
{
    public function test(MonitoredServer $server): ConnectionTestResult
    {
        try {
            $this->connect($server);

            return new ConnectionTestResult(true, 'SSH connection succeeded.');
        } catch (Throwable) {
            return new ConnectionTestResult(false, 'SSH authentication failed. Check the host, port, username, and password.');
        }
    }

    public function execute(MonitoredServer $server, string $command): RemoteCommandResult
    {
        try {
            $ssh = $this->connect($server);
            $output = $ssh->exec($command);

            return new RemoteCommandResult(true, $output, null);
        } catch (Throwable) {
            return new RemoteCommandResult(false, null, 'SSH connection or command execution failed.');
        }
    }

    private function connect(MonitoredServer $server): SSH2
    {
        $ssh = new SSH2($server->hostname, $server->ssh_port, 10);

        if (! $ssh->login($server->ssh_username, $server->ssh_password)) {
            throw new \RuntimeException('SSH login failed.');
        }

        return $ssh;
    }
}
