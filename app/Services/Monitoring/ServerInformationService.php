<?php

namespace App\Services\Monitoring;

use App\Models\MonitoredServer;
use RuntimeException;

class ServerInformationService
{
    public function __construct(private readonly RemoteCommandService $commands) {}

    /** @return array<string, int|string|\DateTimeInterface|null> */
    public function collect(MonitoredServer $server): array
    {
        $result = $this->commands->execute($server, $this->command());

        if (! $result->successful || ! is_string($result->output)) {
            throw new RuntimeException($result->message ?? 'Unable to collect server information.');
        }

        $lines = array_map('trim', preg_split('/\R/', trim($result->output)) ?: []);

        if (count($lines) !== 7) {
            throw new RuntimeException('The remote server returned incomplete system information.');
        }

        return [
            'system_hostname' => $this->text($lines[0]),
            'operating_system' => $this->text($lines[1]),
            'kernel_version' => $this->text($lines[2]),
            'cpu_model' => $this->text($lines[3]),
            'cpu_core_count' => $this->positiveInteger($lines[4]),
            'total_ram_bytes' => $this->positiveInteger($lines[5]),
            'total_disk_bytes' => $this->positiveInteger($lines[6]),
            'last_successful_connection_at' => now(),
        ];
    }

    private function command(): string
    {
        return "sh -c 'hostname; if [ -r /etc/os-release ]; then . /etc/os-release; printf \"%s\\n\" \"\${PRETTY_NAME:-unknown}\"; else uname -s; fi; uname -r; awk -F: '\''/model name|Hardware|Processor/ {gsub(/^[ \\t]+/, \"\", \$2); print \$2; exit}'\'' /proc/cpuinfo 2>/dev/null; getconf _NPROCESSORS_ONLN 2>/dev/null || nproc 2>/dev/null || echo 0; awk '\''/MemTotal:/ {print \$2 * 1024; exit}'\'' /proc/meminfo 2>/dev/null; df -Pk 2>/dev/null | awk '\''NR > 1 {total += \$2} END {print total * 1024}'\'''";
    }

    private function text(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    private function positiveInteger(string $value): ?int
    {
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
