<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;

class ServerStatusService
{
    public function online(MonitoredServer $server): void
    {
        $server->update([

            'is_online' => true,
            'last_checked_at' => now(),
            'last_error' => null,

        ]);
    }

    public function offline(
        MonitoredServer $server,
        string $message
    ): void
    {
        $server->update([

            'is_online' => false,
            'last_checked_at' => now(),
            'last_error' => $message,

        ]);
    }
}