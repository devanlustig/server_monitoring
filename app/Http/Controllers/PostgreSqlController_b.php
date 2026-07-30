<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\PostgreSqlCollector;
use App\Services\Monitoring\Collectors\PostgreSqlConnectionCollector;
use App\Services\Monitoring\PostgreSqlService;
use App\Services\Monitoring\PostgreSqlIncidentService;
use Illuminate\View\View;
use Illuminate\Http\Request;

class PostgreSqlController extends Controller
{
    public function show(MonitoredServer $server,PostgreSqlCollector $summaryCollector,
        PostgreSqlConnectionCollector $connectionCollector,
        PostgreSqlIncidentService $incident,
    ): View {
        $connections = $connectionCollector->collect($server);
        /*
        |--------------------------------------------------------------------------
        | Filter
        |--------------------------------------------------------------------------
        */
        $connections = collect($connections);

        if ($client = request('client')) {
            $connections = $connections->where(
                'client',
                $client
            );
        }

        if ($application = request('application')) {
            $connections = $connections->where(
                'application',
                $application
            );
        }

        if ($database = request('database')) {
            $connections = $connections->where(
                'database',
                $database
            );
        }

        if ($state = request('state')) {
            $connections = $connections->where(
                'state',
                strtolower($state)
            );
        }

        $connections = $connections->values()->all();

        $sort = request('sort', 'activityDuration');
        $direction = request('direction', 'desc');
        usort($connections, function ($a, $b) use ($sort, $direction) {
            $left = data_get($a, $sort);
            $right = data_get($b, $sort);

            if ($sort === 'connectionAge') {
                $left = $a->connectionAgeInSeconds();
                $right = $b->connectionAgeInSeconds();
            }

            if ($sort === 'activityDuration') {
                $left = $a->activityDurationInSeconds();
                $right = $b->activityDurationInSeconds();
            }

            if ($direction === 'asc') {
                return $left <=> $right;
            }
            return $right <=> $left;
        });

            /*
            |--------------------------------------------------------------------------
            | Analytics
            |--------------------------------------------------------------------------
            */

            $topClient = collect($connections)
                ->groupBy('client')
                ->map->count()
                ->sortDesc()
                ->take(5);

            $topApplication = collect($connections)
                ->groupBy(function ($item) {

                    return blank($item->application)
                        ? 'Unknown'
                        : $item->application;

                })
                ->map->count()
                ->sortDesc()
                ->take(5);

            $oldestConnection = collect($connections)
                ->sortByDesc(fn ($c) => $c->connectionAgeInSeconds())
                ->first();

            $longestIdle = collect($connections)
                ->filter(fn ($c) => strtolower($c->state) === 'idle')
                ->sortByDesc(fn ($c) => $c->activityDurationInSeconds())
                ->first();

            $clients = collect($connectionCollector->collect($server))
            ->pluck('client')
            ->unique()
            ->sort()
            ->values();

            $applications = collect($connectionCollector->collect($server))
                            ->pluck('application')
                            ->unique()
                            ->sort()
                            ->values();

            $databases = collect($connectionCollector->collect($server))
                        ->pluck('database')
                        ->unique()
                        ->sort()
                        ->values();

        return view('servers.postgresql', [
            'server' => $server,
            'summary' => $summaryCollector->collect($server),
            'connections' => $connections,
            'topClient' => $topClient,
            'topApplication' => $topApplication,
            'oldestConnection' => $oldestConnection,
            'longestIdle' => $longestIdle,
            'clients'=>$clients,
            'applications'=>$applications,
            'databases'=>$databases,
        ]);
    }

    public function terminate(MonitoredServer $server,int $pid,
        PostgreSqlService $postgres,
        PostgreSqlIncidentService $incident,
    )
    {
        logger()->info('==============================');
        logger()->info('POSTGRES TERMINATE START');
        logger()->info([
            'server' => $server->name,
            'pid'    => $pid,
            'time'   => now()->toDateTimeString(),
        ]);
        /*
        BEFORE SNAPSHOT
        */

        $beforeCount = $postgres->connectionCount(
            $server
        );

        logger()->info('Connection Count Before', [
            'count' => $beforeCount,
        ]);

        $topBefore = $postgres->topClients(
            $server
        );

        logger()->info(
            'Top Client Before',
            $topBefore
        );

        try {

            $before = $incident->captureSnapshot(
                $server,
                'before_terminate'
            );

            logger()->info('Before Snapshot', [
                'file' => $before,
            ]);

        } catch (\Throwable $e) {

            logger()->error('Before Snapshot Failed', [
                'message' => $e->getMessage(),
            ]);

        }

        /*
        TERMINATE
        */

        logger()->info(
            'Top Application Before',
            $postgres->topApplications($server)
        );

        try {

            $result = $postgres->terminate(
                $server,
                $pid
            );

            logger()->info('Terminate Result', [
                'result' => $result,
            ]);

        } catch (\Throwable $e) {

            logger()->error('Terminate Failed', [
                'message' => $e->getMessage(),
            ]);

            return back()->with(
                'error',
                'Terminate failed : ' . $e->getMessage()
            );
        }

        logger()->info(
            'Top Application After',
            $postgres->topApplications($server)
        );

        /*
        WAIT
        */
        sleep(2);

        try {
            $afterCount = $postgres->connectionCount(
                $server
            );
            logger()->info('Connection Count After', [
                'count' => $afterCount,
            ]);
        } catch (\Throwable $e) {
            logger()->error(
                'Connection Count After Failed',
                [
                    'message' => $e->getMessage(),
                ]
            );
        }

        try {

            $topAfter = $postgres->topClients(
                $server
            );
            logger()->info(
                'Top Client After',
                $topAfter
            );

        } catch (\Throwable $e) {
            logger()->error(
                'Top Client After Failed',
                [
                    'message' => $e->getMessage(),
                ]
            );
        }

        /*
        AFTER SNAPSHOT
        */
        try {

            $after = $incident->captureSnapshot(
                $server,
                'after_terminate'
            );

            logger()->info('After Snapshot', [
                'file' => $after,
            ]);

        } catch (\Throwable $e) {

            logger()->error('After Snapshot Failed', [
                'message' => $e->getMessage(),
            ]);

        }

        logger()->info('POSTGRES TERMINATE FINISHED');
        logger()->info('==============================');

        return back()->with(
            'success',
            'Connection terminated successfully.'
        );
    }

    public function killIdle(MonitoredServer $server,PostgreSqlService $postgres,)
    {
        try {

            $count = $postgres->killIdle(
                $server
            );

            return back()->with(
                'success',
                "{$count} idle connection terminated."
            );

        } catch (\Throwable $e) {

            return back()->with(
                'error',
                $e->getMessage()
            );

        }
    }

    public function killIdleOlder(MonitoredServer $server,Request $request,PostgreSqlService $postgres,)
    {
        $minutes = (int) $request->minutes;

        try {

            $count = $postgres->killIdleOlderThan(
                $server,
                $minutes
            );

            return back()->with(
                'success',
                "{$count} idle connection(s) older than {$minutes} minute(s) terminated."
            );

        } catch (\Throwable $e) {

            return back()->with(
                'error',
                $e->getMessage()
            );

        }
    }


    public function killSelected(Request $request,MonitoredServer $server,PostgreSqlService $postgres,)
    {
        logger()->info('HTTP REQUEST KILL MULTIPLE');
        $count = $postgres->terminateMany(
            $server,
            $request->input(
                'pids',
                []
            )
        );
        return back()->with(
            'success',
            "{$count} connection(s) terminated."
        );

    }


    // private function durationToSeconds(string $duration): int
    // {
    //     if (! preg_match('/(\d+):(\d+):(\d+)/', $duration, $match)) {
    //         return 0;
    //     }
    //     return
    //         ($match[1] * 3600)
    //         + ($match[2] * 60)
    //         + $match[3];
    // }
}
