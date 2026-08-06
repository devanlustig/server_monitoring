<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\PostgreSqlCollector;
use App\Services\Monitoring\Collectors\PostgreSqlConnectionCollector;
use App\Services\Monitoring\PostgreSqlService;
use App\Services\Monitoring\PostgreSqlIncidentService;
use App\Services\Monitoring\RemoteCommandService;
use Illuminate\View\View;
use Illuminate\Http\Request;

class PostgreSqlController extends Controller
{

    public function __construct(
        private readonly RemoteCommandService $commands
    ) {
    }

    public function show(MonitoredServer $server,PostgreSqlCollector $summaryCollector,PostgreSqlConnectionCollector $connectionCollector,
        PostgreSqlIncidentService $incident,
    ): View {

        $controllerStart = microtime(true);

        /*
        | Collect Data
        */
        $t = microtime(true);
        $results = $this->commands->executeMany(
            $server,
            [
                'summary'     => $summaryCollector->command($server),
                'connections' => $connectionCollector->command($server),
            ]
        );
        $summary = $summaryCollector->parseOutput(
            $results->get('summary')
        );
        $connections = collect(
            $connectionCollector->parseOutput(
                $results->get('connections')
            )
        );

        /*
        | Filter
        */
        $t = microtime(true);
        if ($client = request('client')) {
            $connections = $connections->where('client', $client);
        }
        if ($application = request('application')) {
            $connections = $connections->where('application', $application);
        }
        if ($database = request('database')) {
            $connections = $connections->where('database', $database);
        }
        if ($state = request('state')) {
            $connections = $connections->where(
                'state',
                strtolower($state)
            );
        }

        /*
        | Sort
        */
        $t = microtime(true);
        $sortedConnections = $connections
            ->values()
            ->all();
        $sort = request('sort', 'activityDuration');
        $direction = request('direction', 'desc');

        usort($sortedConnections, function ($a, $b) use ($sort, $direction) {
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
            return $direction === 'asc'
                ? $left <=> $right
                : $right <=> $left;
        });

        $connections = collect($sortedConnections);

        /*
        | Analytics
        */
        $t = microtime(true);
        $topClient = $connections
            ->groupBy('client')
            ->map
            ->count()
            ->sortDesc()
            ->take(5);

        $topApplication = $connections
            ->groupBy(fn ($item) =>
                blank($item->application)
                    ? 'Unknown'
                    : $item->application
            )
            ->map
            ->count()
            ->sortDesc()
            ->take(5);

        $oldestConnection = $connections
            ->sortByDesc(fn ($c) => $c->connectionAgeInSeconds())
            ->first();

        $longestIdle = $connections
            ->filter(fn ($c) => strtolower($c->state) === 'idle')
            ->sortByDesc(fn ($c) => $c->activityDurationInSeconds())
            ->first();

        /*
        | Filter Dropdown
        */
        $t = microtime(true);

        $clients = $connections
            ->pluck('client')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $applications = $connections
            ->pluck('application')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $databases = $connections
            ->pluck('database')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        /*
        | View
        */
        $t = microtime(true);

        $view = view('servers.postgresql', [

            'server' => $server,
            'summary' => $summary,
            'connections' => $connections->all(),
            'topClient' => $topClient,
            'topApplication' => $topApplication,
            'oldestConnection' => $oldestConnection,
            'longestIdle' => $longestIdle,
            'clients' => $clients,
            'applications' => $applications,
            'databases' => $databases,

        ]);

        return $view;
    }

    public function terminate(MonitoredServer $server,int $pid,PostgreSqlService $postgres,)
    {
        $result = $postgres->terminate(
            $server,
            $pid
        );

        if (! $result) {
            return back()->with(
                'error',
                'Terminate failed.'
            );
        }

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


        $pids = $request->input(
        'pids',
            []
        );

        if (empty($pids)) {
            return back()->with(
                'warning',
                'No connection selected.'
            );
        }
        
        $count = $postgres->terminateMany(
            $server,
            $pids
        );

        return back()->with(
            'success',
            "{$count} connection(s) terminated."
        );

    }

    public function restart(MonitoredServer $server,PostgreSqlService $postgres,)
    {
        $postgres->restart($server);

        return back()->with(
            'success',
            'PostgreSQL service restarted successfully.'
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
