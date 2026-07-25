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

    public function terminateMany(
        MonitoredServer $server,
        array $pids,
    ): int {

        $pids = array_map('intval', $pids);

        $pids = array_filter($pids);

        if (empty($pids)) {
            return 0;
        }

        $list = implode(',', $pids);

        $sql = <<<SQL
    SELECT pg_terminate_backend(pid)
    FROM pg_stat_activity
    WHERE pid IN ({$list});
    SQL;

        $result = $this->runSql(
            $server,
            $sql
        );

        return substr_count(
            $result,
            't'
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Execute SQL
    |--------------------------------------------------------------------------
    */

    private function runSql(
        MonitoredServer $server,
        string $sql,
    ): string {

        $command = $this->builder->build(
            $server,
            $sql
        );

        $result = $this->commands->execute(
            $server,
            $command
        );

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
}