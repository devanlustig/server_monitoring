<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\PostgreSqlSummaryData;
use App\Services\Monitoring\Parsers\PostgreSqlParser;
use App\Services\Monitoring\RemoteCommandService;
use App\Services\Monitoring\Support\PostgreSqlCommandBuilder;
use RuntimeException;

class PostgreSqlCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly PostgreSqlParser $parser,
        private readonly PostgreSqlCommandBuilder $builder,
    ) {
    }

    public function collect(MonitoredServer $server): PostgreSqlSummaryData
    {
        $result = $this->commands->execute(
            $server,
            $this->builder->build(
                $server,
                $this->sql()
            )
        );

        if (! $result->successful) {
            throw new RuntimeException(
                $result->message ?? 'Unable to collect PostgreSQL information.'
            );
        }
        //dd($result->output);
        return $this->parser->parse($result->output);
    }

    private function sql(): string
    {
        return <<<'SQL'
    SELECT
        count(*),
        current_setting('max_connections'),
        COALESCE(sum(case when state='active' then 1 else 0 end),0),
        COALESCE(sum(case when state='idle' then 1 else 0 end),0),
        COALESCE(sum(case when state='idle in transaction' then 1 else 0 end),0),
        pg_size_pretty(pg_database_size(current_database()))
    FROM pg_stat_activity;
    SQL;
    }
}