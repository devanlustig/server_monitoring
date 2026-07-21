<?php

namespace App\Services\Monitoring;

use App\Models\CpuMetric;
use App\Models\MonitoredServer;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CpuMonitorService
{
    public function __construct(private readonly RemoteCommandService $commands) {}

    /** Collect one Linux CPU sample through the server's configured connection. */
    public function collect(MonitoredServer $server): CpuMetric
    {
        $result = $this->commands->execute($server, $this->linuxCpuCommand());

        if (! $result->successful || ! is_string($result->output)) {
            throw new RuntimeException($result->message ?? 'Unable to collect CPU metrics.');
        }

        [$total, $idle, $load1, $load5, $load15, $cores] = $this->parseOutput($result->output);
        $usagePercent = $this->usagePercent($server, $total, $idle);

        $server->forceFill(['last_successful_connection_at' => now()])->save();

        return CpuMetric::query()->create([
            'server_id' => $server->id,
            'hostname' => $server->hostname,
            'usage_percent' => $usagePercent,
            'load_1' => $load1,
            'load_5' => $load5,
            'load_15' => $load15,
            'core_count' => $cores,
            'collected_at' => now(),
        ]);
    }

    private function linuxCpuCommand(): string
    {
        return "sh -c 'read cpu user nice system idle iowait irq softirq steal guest guest_nice < /proc/stat; total=\$((user+nice+system+idle+iowait+irq+softirq+steal)); idle_total=\$((idle+iowait)); set -- \$(cat /proc/loadavg); cores=\$(getconf _NPROCESSORS_ONLN 2>/dev/null || nproc 2>/dev/null || echo 0); printf \"%s|%s|%s|%s|%s|%s\\n\" \"\$total\" \"\$idle_total\" \"\$1\" \"\$2\" \"\$3\" \"\$cores\"'";
    }

    /** @return array{int, int, ?float, ?float, ?float, ?int} */
    private function parseOutput(string $output): array
    {
        $parts = explode('|', trim($output));

        if (count($parts) !== 6 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            throw new RuntimeException('The remote server returned an invalid CPU response.');
        }

        return [
            (int) $parts[0],
            (int) $parts[1],
            $this->decimal($parts[2]),
            $this->decimal($parts[3]),
            $this->decimal($parts[4]),
            ctype_digit($parts[5]) && (int) $parts[5] > 0 ? (int) $parts[5] : null,
        ];
    }

    private function usagePercent(MonitoredServer $server, int $total, int $idle): ?float
    {
        $key = "monitoring.cpu.{$server->id}.previous";
        $previous = Cache::get($key);
        Cache::put($key, compact('total', 'idle'), now()->addMinutes(5));

        if (! is_array($previous) || ! isset($previous['total'], $previous['idle'])) {
            return null;
        }

        $totalDelta = $total - $previous['total'];
        $idleDelta = $idle - $previous['idle'];

        return $totalDelta > 0 && $idleDelta >= 0
            ? round(max(0, min(100, (1 - ($idleDelta / $totalDelta)) * 100)), 2)
            : null;
    }

    private function decimal(string $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
