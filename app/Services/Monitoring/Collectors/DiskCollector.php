<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\DiskMetricData;
use App\Services\Monitoring\Parsers\DiskParser;
use App\Services\Monitoring\RemoteCommandService;
use RuntimeException;

class DiskCollector
{
    public function __construct(

        private readonly RemoteCommandService $commands,

        private readonly DiskParser $parser,

    ){}

    public function collect(
        MonitoredServer $server
    ): DiskMetricData
    {
        $result = $this->commands->execute(
            $server,
            $this->command()
        );

        if(!$result->successful){

            throw new RuntimeException(
                $result->message
            );

        }

        return $this->parser->parse(
            $result->output
        );
    }

    private function command(): string
    {
        return "df -B1 / | tail -1";
    }
}