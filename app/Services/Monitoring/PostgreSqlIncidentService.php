<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\PostgreSqlIncidentCollector;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class PostgreSqlIncidentService
{
    public function __construct(
        private readonly PostgreSqlIncidentCollector $collector,
    ) {
    }

    public function capture(
    MonitoredServer $server
    )
    {
        $path = $this->captureSnapshot(
            $server,
            'manual'
        );

        return response()->download($path);
    }

    private function buildReport($incident): string
    {
        $lines = [];
        $lines[] = str_repeat('=', 90);
        $lines[] = 'POSTGRESQL INCIDENT REPORT';
        $lines[] = str_repeat('=', 90);
        $lines[] = '';
        $lines[] = 'Server       : ' . $incident->server;
        $lines[] = 'Captured At  : ' . $incident->capturedAt;
        $lines[] = '';

        foreach ($incident->sections as $title => $content) {

            $lines[] = str_repeat('-', 90);
            $lines[] = strtoupper($title);
            $lines[] = str_repeat('-', 90);
            switch ($title) {
                case 'Summary':
                    $lines = array_merge(
                        $lines,
                        $this->formatSummary($content)
                    );
                    break;
                default:
                    $lines = array_merge(
                        $lines,
                        $this->formatTable($content)
                    );
                    break;
            }
            $lines[] = '';
        }
        return implode(PHP_EOL, $lines);
    }

    private function formatSummary(string $content): array
    {
        $content = trim($content);
        if (str_starts_with($content, 'ERROR')) {
            return [$content];
        }
        $parts = explode('|', $content);
        if (count($parts) < 6) {
            return [$content];
        }
        $current = (int)$parts[0];
        $max = (int)$parts[1];
        $usage = $max > 0
            ? round(($current / $max) * 100, 2)
            : 0;
        return [
            sprintf("Current Connection : %s", number_format($current)),
            sprintf("Max Connection     : %s", number_format($max)),
            sprintf("Usage              : %s %%", $usage),
            sprintf("Active             : %s", $parts[2]),
            sprintf("Idle               : %s", $parts[3]),
            sprintf("Idle Transaction   : %s", $parts[4]),
            sprintf("Database Size      : %s", $parts[5]),

        ];
    }

    private function formatTable(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return ['No Data'];
        }

        if (str_starts_with($content, 'ERROR')) {
            return [$content];
        }
        $rows = explode(PHP_EOL, $content);
        $result = [];
        foreach ($rows as $row) {
            $columns = explode('|', $row);
            $result[] = implode(
                '    ',
                array_map(
                    'trim',
                    $columns
                )
            );
        }
        return $result;
    }

    public function captureSnapshot(
    MonitoredServer $server,
    string $prefix = 'snapshot'
        ): string {

            $incident = $this->collector->collect($server);

            $directory = 'incidents/postgresql';

            Storage::disk('local')->makeDirectory($directory);

            $filename = sprintf(
                '%s/%s_%s_%s.txt',
                $directory,
                $prefix,
                $server->name,
                now()->format('Ymd_His_u')
            );

            Storage::disk('local')->put(
                $filename,
                $this->buildReport($incident)
            );

            return Storage::disk('local')->path($filename);

    }
}