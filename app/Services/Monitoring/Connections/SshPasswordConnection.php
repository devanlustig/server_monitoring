<?php

namespace App\Services\Monitoring\Connections;

use App\Domain\Monitoring\Connections\ServerConnection;
use App\Domain\Monitoring\Data\ConnectionTestResult;
use App\Domain\Monitoring\Data\RemoteCommandResult;
use App\Domain\Monitoring\Data\BatchCommandResult;
use App\Models\MonitoredServer;
use phpseclib3\Net\SSH2;
use Throwable;

class SshPasswordConnection implements ServerConnection
{
    private ?SSH2 $ssh = null;
    private ?int $connectedServerId = null;

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

    public function executeMany(
        MonitoredServer $server,
        array $commands
    ): BatchCommandResult {

        $ssh = $this->connect($server);

        $script = '';

        foreach ($commands as $key => $command) {

            $script .= "echo '__BEGIN__{$key}__'\n";
            $script .= "(\n";
            $script .= $command . "\n";
            $script .= ")\n";
            $script .= "echo '__END__{$key}__'\n";

        }

        $output = $ssh->exec($script);

        return new BatchCommandResult(
            $this->parseBatchOutput($output)
        );
    }

    private function parseBatchOutput(
        string $output
    ): array {

        $results = [];
        preg_match_all(
            '/__BEGIN__(.*?)__([\s\S]*?)__END__\\1__/',
            $output,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $results[$match[1]] = trim($match[2]);

        }
        return $results;
    }

}
