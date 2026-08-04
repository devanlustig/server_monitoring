<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\Parsers\ApacheParser;
use App\Services\Monitoring\RemoteCommandService;
use App\Services\Monitoring\Support\ApacheCommandBuilder;
use RuntimeException;

class ApacheCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands,
        private readonly ApacheParser $parser,
        private readonly ApacheCommandBuilder $builder,
    ) {}

    public function collect(MonitoredServer $server, int $lines = 5000): array
    {
        $command = $this->command($server, $lines);
        logger()->info('Apache Command', [
            'command' => $command,
        ]);

        $result = $this->commands->execute($server, $command);

        logger()->info('Apache Result', [
            'success' => $result->successful,
            'output' => $result->output,
        ]);

        if (! $result->successful) {
            throw new RuntimeException(
                $result->message ?? 'Unable to collect Apache log via SSH.'
            );
        }

        return $this->parser->parse($result->output);
    }

    public function command(MonitoredServer $server, int $lines = 5000): string
    {
        return $this->builder->build($server, $lines);
    }

    public function parseOutput(string $output): array
    {
        return $this->parser->parse($output);
    }
}
