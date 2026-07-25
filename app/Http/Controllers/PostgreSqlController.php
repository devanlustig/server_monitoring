<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\PostgreSqlCollector;
use App\Services\Monitoring\Collectors\PostgreSqlConnectionCollector;
use App\Services\Monitoring\PostgreSqlService;
use Illuminate\View\View;
use Illuminate\Http\Request;

class PostgreSqlController extends Controller
{
    public function show(MonitoredServer $server,PostgreSqlCollector $summaryCollector,
        PostgreSqlConnectionCollector $connectionCollector,
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

    public function terminate(MonitoredServer $server,int $pid,PostgreSqlService $postgres,)
    {
        try {

            $postgres->terminate(
                $server,
                $pid
            );

            return back()->with(
                'success',
                "PID {$pid} terminated."
            );

        } catch (\Throwable $e) {

            return back()->with(
                'error',
                $e->getMessage()
            );

        }
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
