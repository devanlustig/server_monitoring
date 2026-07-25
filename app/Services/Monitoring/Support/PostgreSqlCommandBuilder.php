<?php

namespace App\Services\Monitoring\Support;

use App\Models\MonitoredServer;

class PostgreSqlCommandBuilder
{
    public function build(
        MonitoredServer $server,
        string $sql,
        bool $showHeader = false,
    ): string {

        $encoded = base64_encode(trim($sql));

        $header = $showHeader ? '' : '-t';

        return "cd /tmp && "
            . "echo '{$encoded}' | "
            . "base64 -d | "
            . "sudo -H -u postgres "
            . "psql "
            . "-p {$server->postgres_port} "
            . "-A "
            . "{$header} "
            . "-F \"|\"";
    }
}