<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Parsers\PostgreSqlConnectionParser;
use App\Services\Monitoring\RemoteCommandService;
use App\Services\Monitoring\Support\PostgreSqlCommandBuilder;
use RuntimeException;

class PostgreSqlConnectionCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly PostgreSqlConnectionParser $parser,
        private readonly PostgreSqlCommandBuilder $builder,
    ) {
    }

    public function collect(
        MonitoredServer $server
    ): array {

        $result = $this->commands->execute(

            $server,

            $this->builder->build(
                $server,
                $this->sql()
            )

        );

        if (! $result->successful) {

            throw new RuntimeException(
                $result->message ?? 'Unable to collect PostgreSQL connections.'
            );

        }
        //dd($result->output);
        return $this->parser->parse(
            $result->output
        );
    }

    private function sql(): string
    {
        return <<<'SQL'
    SELECT
        pid,
        datname,
        usename,
        COALESCE(application_name,'Unknown'),
        COALESCE(client_addr::text,'Local Socket'),
        state,
        to_char(backend_start,'YYYY-MM-DD HH24:MI:SS'),
        to_char(state_change,'YYYY-MM-DD HH24:MI:SS'),
        to_char(now() - backend_start,'DD "Day" HH24:MI:SS'),

        CASE
            WHEN state='active'
                THEN to_char(now()-query_start,'HH24:MI:SS')
            ELSE
                to_char(now()-state_change,'HH24:MI:SS')
        END,

        replace(
            replace(query,E'\n',' '),
            E'\r',' '
        )

    FROM pg_stat_activity
    WHERE
        pid <> pg_backend_pid()

    ORDER BY
        CASE
            WHEN state='active' THEN 0
            ELSE 1
        END,
        now()-state_change DESC;
    SQL;
    }
}