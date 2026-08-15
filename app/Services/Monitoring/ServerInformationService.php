<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use RuntimeException;

class ServerInformationService
{
    public function __construct(
        private readonly RemoteCommandService $commands
    ) {}

    /**
     * Collect static information from remote server.
     *
     * @return array<string,mixed>
     */
    public function collect(MonitoredServer $server): array
    {
        $result = $this->commands->execute($server, $this->command());

        if (! $result->successful || ! is_string($result->output)) {
            throw new RuntimeException(
                $result->message ?? 'Unable to collect server information.'
            );
        }

        $info = $this->parseOutput($result->output);

        return [
            'system_hostname'              => $this->string($info['HOSTNAME'] ?? null),
            'operating_system'             => $this->string($info['OS'] ?? null),
            'kernel_version'               => $this->string($info['KERNEL'] ?? null),
            'cpu_model'                    => $this->string($info['CPU_MODEL'] ?? null),
            'cpu_core_count'               => $this->integer($info['CPU_CORES'] ?? null),
            'total_ram_bytes'              => $this->integer($info['RAM_BYTES'] ?? null),
            'total_disk_bytes'             => $this->integer($info['DISK_BYTES'] ?? null),

            'uptime'                       => $this->string($info['UPTIME']??null),
            'load_average'                 => $this->string($info['LOADAVG']??null),
            'web_server'                   => $this->string($info['WEB_SERVER'] ?? null),

            // untuk pengembangan berikutnya
            // 'architecture' => $this->string($info['ARCH'] ?? null),
            // 'ip_address'   => $this->string($info['IP'] ?? null),

            'last_successful_connection_at' => now(),

        ];
    }

    private function command(): string
    {
        return <<<'BASH'
        echo "HOSTNAME=$(hostname)"

        if [ -r /etc/os-release ]; then
            . /etc/os-release
            echo "OS=${PRETTY_NAME}"
        else
            echo "OS=$(uname -s)"
        fi

        echo "KERNEL=$(uname -r)"

        CPU_MODEL=$(lscpu 2>/dev/null | awk -F: '/Model name/ {gsub(/^[ \t]+/, "", $2); print $2; exit}')

        if [ -z "$CPU_MODEL" ]; then
            CPU_MODEL=$(awk -F: '/model name|Hardware|Processor/ {
                gsub(/^[ \t]+/, "", $2);
                print $2;
                exit
            }' /proc/cpuinfo)
        fi

        echo "CPU_MODEL=${CPU_MODEL}"

        echo "CPU_CORES=$(getconf _NPROCESSORS_ONLN 2>/dev/null || nproc)"

        echo "RAM_BYTES=$(awk '/MemTotal:/ {print $2*1024}' /proc/meminfo)"

        echo "DISK_BYTES=$(df -B1 --output=size / | tail -1 | tr -d ' ')"
        echo "UPTIME=$(uptime -p)"
        echo "LOADAVG=$(cut -d' ' -f1-3 /proc/loadavg)"
        if command -v nginx >/dev/null 2>&1; then
            echo "WEB_SERVER=nginx"
        elif command -v apache2 >/dev/null 2>&1; then
            echo "WEB_SERVER=apache"
        elif command -v httpd >/dev/null 2>&1; then
            echo "WEB_SERVER=apache"
        else
            echo "WEB_SERVER=unknown"
        fi
        BASH;
    }

    /**
     * Parse KEY=VALUE output.
     */
    private function parseOutput(string $output): array
    {
        $data = [];

        foreach (preg_split('/\R/', trim($output)) as $line) {

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $data[trim($key)] = trim($value);
        }

        return $data;
    }

    private function string(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }

    private function integer(?string $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}