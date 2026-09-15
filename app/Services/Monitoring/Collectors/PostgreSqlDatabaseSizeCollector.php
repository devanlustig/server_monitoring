<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Models\PostgresqlDatabaseSizeSnapshot;
use App\Services\Monitoring\RemoteCommandService;
use App\Services\Monitoring\Support\PostgreSqlCommandBuilder;
use Exception;
use Throwable;

class PostgreSqlDatabaseSizeCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly PostgreSqlCommandBuilder $builder,
    ) {}

    public function collect(MonitoredServer $server): void
    {
        if (empty($server->postgres_port)) {
            return;
        }

        try {
            $sql = <<<'SQL'
SELECT oid, datname, pg_database_size(datname) AS size_bytes
FROM pg_database
WHERE datallowconn = true;
SQL;

            $command = $this->builder->build($server, $sql);
            $result = $this->commands->execute($server, $command);

            if (!$result || !$result->successful || empty($result->output)) {
                return;
            }

            $snapshotAt = now();
            $records = [];
            $lines = explode("\n", trim($result->output));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $parts = explode('|', $line);
                if (count($parts) >= 3) {
                    $oid = trim($parts[0]);
                    $name = trim($parts[1]);
                    $size = (int) trim($parts[2]);

                    $records[] = [
                        'server_id' => $server->id,
                        'database_oid' => $oid,
                        'database_name' => $name,
                        'size_bytes' => $size,
                        'snapshot_at' => $snapshotAt,
                    ];
                }
            }

            if (!empty($records)) {
                PostgresqlDatabaseSizeSnapshot::insert($records);
            }
        } catch (Throwable $e) {
            logger()->warning("PostgreSqlDatabaseSizeCollector failed for server {$server->name}: " . $e->getMessage());
        }
    }
}
