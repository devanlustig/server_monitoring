<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use App\Services\Monitoring\RemoteCommandService;

class ServerStatusService
{
    public function __construct(
        private readonly RemoteCommandService $commands,
    ){}

    public function online(MonitoredServer $server): void
    {
        $server->update([

            'is_online' => true,
            'last_checked_at' => now(),
            'last_error' => null,

        ]);
    }

    public function offline(MonitoredServer $server,string $message): void
    {
        $server->update([

            'is_online' => false,
            'last_checked_at' => now(),
            'last_error' => $message,

        ]);
    }

    public function refresh(MonitoredServer $server): void
    {
        $result = $this->commands->execute(
            $server,
            $this->command()
        );

        if(!$result->successful){
            return;
        }

        $data = $this->parse($result->output);
        $server->update([

            'uptime'=>$data['uptime'],
            'load_average'=>$data['load_average'],
            'last_successful_connection_at'=>now(),
            'last_checked_at'=>now(),

        ]);
    }

    private function command(): string
    {
        return <<<'BASH'
    echo "UPTIME=$(uptime -p)"
    echo "LOADAVG=$(cut -d' ' -f1-3 /proc/loadavg)"
    BASH;
    }

    private function parse(string $output): array
    {
        $data=[];
        foreach(preg_split('/\R/',trim($output)) as $line){

            if(!str_contains($line,'=')){
                continue;
            }

            [$key,$value]=explode('=',$line,2);
            $data[trim($key)]=trim($value);
        }

        return[
            'uptime'=>$data['UPTIME']??null,
            'load_average'=>$data['LOADAVG']??null,
        ];
    }
}