<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Support\PostgreSqlCommandBuilder;
use RuntimeException;
use Throwable;

class PostgreSqlService
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly PostgreSqlCommandBuilder $builder,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Terminate Single PID
    |--------------------------------------------------------------------------
    */

    public function terminate(
        MonitoredServer $server,
        int $pid,
    ): bool {

        $checkSql = <<<SQL
        SELECT state
        FROM pg_stat_activity
        WHERE pid = {$pid};
        SQL;

        $state = trim(
            $this->runSql(
                $server,
                $checkSql
            )
        );

        if ($state === '') {
            throw new RuntimeException(
                "PID {$pid} not found."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Jangan terminate connection yang sedang aktif
        |--------------------------------------------------------------------------
        */

        if (strtolower($state) === 'active') {
            throw new RuntimeException(
                "PID {$pid} is ACTIVE."
            );
        }

        $terminateSql = <<<SQL
SELECT pg_terminate_backend({$pid});
SQL;

        $result = trim(
            $this->runSql(
                $server,
                $terminateSql
            )
        );

        //dd($result);

        if ($result !== 't') {
            throw new RuntimeException(
                "Unable to terminate PID {$pid}."
            );
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Kill All Idle
    |--------------------------------------------------------------------------
    */

    public function killIdle(
        MonitoredServer $server,
    ): int {

        $countSql = <<<SQL
SELECT COUNT(*)
FROM pg_stat_activity
WHERE
    state='idle';
SQL;

        $count = (int) trim(
            $this->runSql(
                $server,
                $countSql
            )
        );

        if ($count === 0) {
            return 0;
        }

        $killSql = <<<SQL
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE
    state='idle';
SQL;

        $this->runSql(
            $server,
            $killSql
        );

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Kill Idle Older Than
    |--------------------------------------------------------------------------
    */

    public function killIdleOlderThan(
        MonitoredServer $server,
        int $minutes,
    ): int {

        if ($minutes < 1) {
            $minutes = 1;
        }

        $countSql = <<<SQL
SELECT COUNT(*)
FROM pg_stat_activity
WHERE
    state='idle'
    AND now() - state_change > interval '{$minutes} minutes';
SQL;

        $count = (int) trim(
            $this->runSql(
                $server,
                $countSql
            )
        );

        if ($count === 0) {
            return 0;
        }

        $killSql = <<<SQL
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE
    state='idle'
    AND now() - state_change > interval '{$minutes} minutes';
SQL;

        $this->runSql(
            $server,
            $killSql
        );

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Kill Selected
    |--------------------------------------------------------------------------
    */

    public function terminateMany(MonitoredServer $server,array $pids,
    ): int {

        logger()->info('==============================');
        logger()->info('TERMINATE MANY START');
        logger()->info([
            'time'  => now()->toDateTimeString(),
            'pids'  => $pids,
            'count' => count($pids),
        ]);

        $pids = array_unique(
            array_filter(
                array_map('intval', $pids)
            )
        );
        if (empty($pids)) {
            return 0;
        }
        $list = implode(',', $pids);
        $sql = <<<SQL
    SELECT pid,pg_terminate_backend(pid)
    FROM pg_stat_activity
    WHERE pid IN ({$list}) AND pid <> pg_backend_pid()
    ORDER BY pid;
    SQL;

        $result = trim(
            $this->runSql(
                $server,
                $sql
            )
        );

        logger()->info('TERMINATE MANY RESULT');
        logger()->info($result);
        logger()->info('==============================');

        return $this->countSuccessfulTerminate(
            $result
        );
    }


    private function countSuccessfulTerminate(string $result,): int {

        if (blank($result)) {
            return 0;
        }
        $success = 0;
        foreach (preg_split('/\R/', trim($result)) as $line) {
            $parts = explode('|', trim($line));
            if (count($parts) !== 2) {
                continue;
            }
            if (trim($parts[1]) === 't') {
                $success++;
            }
        }
        return $success;
    }

    /*
    |--------------------------------------------------------------------------
    | Execute SQL
    |--------------------------------------------------------------------------
    */

    private function runSql(MonitoredServer $server,string $sql,
    ): string {

        $command = $this->builder->build(
            $server,
            $sql
        );

        $result = $this->commands->execute(
            $server,
            $command
        );

        logger()->info('RUN SQL');
        logger()->info([
            'sql' => $sql,
        ]);
        logger()->info([
            'output' => $result->output,
        ]);

        if (! $result->successful) {
            throw new RuntimeException(
                $result->message ?? 'Command execution failed.'
            );
        }

        if (blank($result->output)) {
            throw new RuntimeException(
                'PostgreSQL returned an empty response.'
            );
        }

        return trim($result->output);
    }

    public function executeRawSql(MonitoredServer $server,string $sql,): string {

        return $this->runSql(
            $server,
            $sql
        );

    }

    public function connectionCount(
        MonitoredServer $server,
    ): int {

        $sql = <<<SQL
    SELECT COUNT(*)
    FROM pg_stat_activity;
    SQL;

        $result = $this->runSql(
            $server,
            $sql
        );

        return (int) trim($result);

    }

    public function topClients(
    MonitoredServer $server,
        ): array {

            $sql = <<<SQL
        SELECT
            COALESCE(client_addr::text,'LOCAL'),
            COUNT(*)
        FROM pg_stat_activity
        GROUP BY client_addr
        ORDER BY COUNT(*) DESC;
        SQL;

            $result = $this->runSql(
                $server,
                $sql
            );
            $rows = [];
            foreach (explode(PHP_EOL, trim($result)) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts = explode('|', $line);
                $rows[] = [
                    'client' => trim($parts[0]),
                    'count' => (int) trim($parts[1]),
                ];
            }
            return $rows;

    }

    public function topApplications(
        MonitoredServer $server,
    ): array {

        $sql = <<<SQL
    SELECT
        COALESCE(NULLIF(application_name,''),'Unknown'),
        COUNT(*)
    FROM pg_stat_activity
    GROUP BY application_name
    ORDER BY COUNT(*) DESC;
    SQL;

        $result = $this->runSql(
            $server,
            $sql
        );
        $rows = [];
        foreach (explode(PHP_EOL, trim($result)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode('|', $line);
            $rows[] = [
                'application' => trim($parts[0]),
                'count' => (int) trim($parts[1]),
            ];
        }
        return $rows;

    }

}