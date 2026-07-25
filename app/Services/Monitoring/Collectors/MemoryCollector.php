<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\MemoryMetricData;
use App\Services\Monitoring\Parsers\MemoryParser;
use App\Services\Monitoring\RemoteCommandService;
use RuntimeException;

class MemoryCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly MemoryParser $parser,
    ) {}

    public function collect(MonitoredServer $server): MemoryMetricData
    {
        $result = $this->commands->execute(
            $server,
            'free -b'
        );

        if (! $result->successful) {
            throw new RuntimeException(
                $result->message ?? 'Unable to collect memory metrics.'
            );
        }

        return $this->parser->parse($result->output);
    }
}