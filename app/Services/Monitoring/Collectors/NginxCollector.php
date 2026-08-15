<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\ApacheLogEntryData;
use App\Services\Monitoring\RemoteCommandService;
use RuntimeException;
use DateTimeImmutable;

class NginxCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
    ) {}

    public function collect(MonitoredServer $server): array
    {
        $result = $this->commands->execute(
            $server,
            $this->command()
        );

        if (! $result->successful) {
            throw new RuntimeException(
                $result->message ?? 'Unable to collect Nginx access log.'
            );
        }

        return $this->parseOutput($result->output);
    }

    private function command(): string
    {
        return implode("\n", [
            'LOG="/var/log/nginx/access.log"',
            '',
            'if [ ! -f "$LOG" ]; then',
            '    echo "__LOG_NOT_FOUND__"',
            '    exit 0',
            'fi',
            '',
            'if [ ! -r "$LOG" ]; then',
            '    echo "__LOG_NOT_READABLE__"',
            '    exit 0',
            'fi',
            '',
            'echo "__LOG_FOUND__"',
            '',
            'tail -n 5000 "$LOG"',
        ]);
    }

    private function parseOutput(?string $output): array
    {
        $output = trim((string) $output);

        if ($output === '__LOG_NOT_FOUND__') {
            return [
                'logFound' => false,
                'entries' => [],
            ];
        }

        if ($output === '__LOG_NOT_READABLE__') {
            throw new RuntimeException(
                'Nginx access log exists but is not readable.'
            );
        }

        $lines = preg_split('/\R/', $output);

        $entries = [];

        foreach ($lines as $line) {

            $line = trim($line);

            if (
                $line === '' ||
                $line === '__LOG_FOUND__'
            ) {
                continue;
            }

            $entry = $this->parseLine($line);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return [
            'logFound' => true,
            'entries' => $entries,
        ];
    }

    private function parseLine(string $line): ?ApacheLogEntryData
    {
        $pattern='/^(\S+) - - \[([^\]]+)\] "([^"]*)" (\d{3}) (\d+) "([^"]*)" "(.*)"$/';

        if(!preg_match($pattern,trim($line),$matches)){
            return null;
        }

        $requestParts=preg_split('/\s+/',trim($matches[3]),3);

        $dateTime=DateTimeImmutable::createFromFormat('d/M/Y:H:i:s O',$matches[2]);

        if(!$dateTime){
            return null;
        }

        return new ApacheLogEntryData(
            virtualHost:null,
            ip:$matches[1],
            timestamp:$matches[2],
            dateTime:$dateTime,
            method:$requestParts[0]??'UNKNOWN',
            endpoint:$requestParts[1]??'/',
            statusCode:(int)$matches[4],
            bytes:(int)$matches[5],
            referer:$matches[6]!=='-'?$matches[6]:null,
            userAgent:$matches[7]!=='-'?$matches[7]:null,
            responseTimeMs:null,
        );
    }
}