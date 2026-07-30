<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\PostgreSqlService;
use App\Services\Monitoring\DTO\PostgreSqlIncidentData;

class PostgreSqlIncidentCollector
{
    public function __construct(
        private readonly PostgreSqlService $postgres,
    ) {
    }

    public function collect(MonitoredServer $server): PostgreSqlIncidentData {

        return new PostgreSqlIncidentData(

            capturedAt: now()->toDateTimeString(),
            server: $server->name,
            sections: [
            'Summary' => $this->postgres->executeRawSql(
                $server,
                $this->summarySql()
            ),
            'Top Client' => $this->postgres->executeRawSql(
                $server,
                $this->topClientSql()
            ),
            'Top Application' => $this->postgres->executeRawSql(
                $server,
                $this->topApplicationSql()
            ),
        ],

        );
    }

    private function summarySql(): string
    {
        return <<<'SQL'
    SELECT
        COUNT(*) AS current_connection,
        current_setting('max_connections') AS max_connection,
        COALESCE(SUM(CASE WHEN state = 'active' THEN 1 ELSE 0 END),0) AS active_connection,
        COALESCE(SUM(CASE WHEN state = 'idle' THEN 1 ELSE 0 END),0) AS idle_connection,
        COALESCE(SUM(CASE WHEN state = 'idle in transaction' THEN 1 ELSE 0 END),0) AS idle_transaction,
        pg_size_pretty(pg_database_size(current_database())) AS database_size
    FROM pg_stat_activity;
    SQL;
    }

    private function topClientSql(): string
    {
        return <<<'SQL'
    SELECT
        COALESCE(client_addr::text, 'LOCAL') AS client,
        COUNT(*) AS total
    FROM pg_stat_activity
    GROUP BY client_addr
    ORDER BY total DESC;
    SQL;
    }

    private function topApplicationSql(): string
    {
        return <<<'SQL'
    SELECT
        COALESCE(NULLIF(application_name, ''), 'Unknown') AS application,
        COUNT(*) AS total
    FROM pg_stat_activity
    GROUP BY application_name
    ORDER BY total DESC;
    SQL;
    }
}