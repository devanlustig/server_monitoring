<?php

namespace App\Services\Monitoring\Connections;

use App\Domain\Monitoring\Connections\ServerConnection;
use App\Models\MonitoredServer;
use InvalidArgumentException;

class ServerConnectionFactory
{
    public function for(MonitoredServer $server): ServerConnection
    {
        return match ($server->authentication_method) {
            'ssh_password' => app(SshPasswordConnection::class),
            default => throw new InvalidArgumentException("Unsupported authentication method [{$server->authentication_method}]."),
        };
    }
}
