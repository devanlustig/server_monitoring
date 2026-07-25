<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\CpuMetricData;
use App\Services\Monitoring\Parsers\CpuParser;
use App\Services\Monitoring\RemoteCommandService;
use RuntimeException;

class CpuCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly CpuParser $parser,
    ) {}

    public function collect(MonitoredServer $server): CpuMetricData
    {
        $result = $this->commands->execute(
            $server,
            $this->command()
        );

        if (! $result->successful) {
            throw new RuntimeException(
                $result->message ?? 'Unable to collect CPU metrics.'
            );
        }

        return $this->parser->parse($result->output);
    }

    private function command(): string
    {
        return "sh -c 'head -1 /proc/stat; sleep 1; head -1 /proc/stat; cat /proc/loadavg'";
    }
}