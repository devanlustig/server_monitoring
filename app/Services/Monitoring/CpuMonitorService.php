<?php

namespace App\Services\Monitoring;

use App\Models\CpuMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

class CpuMonitorService
{
    /** Collect and persist a CPU sample for the host running this application. */
    public function collect(): CpuMetric
    {
        $windowsMetrics = $this->isWindows() ? $this->windowsMetrics() : null;
        [$load1, $load5, $load15] = $this->loadAverages();

        return CpuMetric::query()->create([
            'hostname' => $this->hostname(),
            'usage_percent' => $windowsMetrics['usage_percent'] ?? $this->linuxUsagePercent(),
            'load_1' => $load1,
            'load_5' => $load5,
            'load_15' => $load15,
            'core_count' => $windowsMetrics['core_count'] ?? $this->linuxCoreCount(),
            'collected_at' => now(),
        ]);
    }

    /**
     * Read both Windows values with one short-lived PowerShell process.
     * Get-WmiObject supports older Windows PowerShell installations where CIM is absent.
     *
     * @return array{usage_percent: ?float, core_count: ?int}|null
     */
    private function windowsMetrics(): ?array
    {
        try {
            $result = Process::timeout(5)->run(
                'powershell.exe -NoProfile -NonInteractive -Command "$processors = if (Get-Command Get-CimInstance -ErrorAction SilentlyContinue) { Get-CimInstance Win32_Processor } else { Get-WmiObject Win32_Processor }; $usage = ($processors | Measure-Object -Property LoadPercentage -Average).Average; $cores = ($processors | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum; [Console]::WriteLine(($usage.ToString([System.Globalization.CultureInfo]::InvariantCulture)) + [char]124 + $cores)"'
            );

            if (! $result->successful()) {
                return null;
            }

            [$usage, $cores] = array_pad(explode('|', trim($result->output()), 2), 2, null);

            return [
                'usage_percent' => is_numeric($usage) ? round((float) $usage, 2) : null,
                'core_count' => is_numeric($cores) ? (int) $cores : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function linuxUsagePercent(): ?float
    {
        if (! $this->isLinux() || ! is_readable('/proc/stat')) {
            return null;
        }

        $contents = @file_get_contents('/proc/stat');
        $line = is_string($contents) ? strtok($contents, "\n") : false;

        if (! is_string($line) || ! str_starts_with($line, 'cpu ')) {
            return null;
        }

        $values = preg_split('/\s+/', trim(substr($line, 4)));

        if (! is_array($values) || count($values) < 4) {
            return null;
        }

        $counters = array_map('intval', $values);
        $total = array_sum($counters);
        $idle = ($counters[3] ?? 0) + ($counters[4] ?? 0);

        if ($total === 0) {
            return null;
        }

        try {
            $previous = Cache::get('monitoring.cpu.previous');
            Cache::put('monitoring.cpu.previous', compact('total', 'idle'), now()->addMinutes(5));
        } catch (Throwable) {
            return null;
        }

        if (! is_array($previous) || ! isset($previous['total'], $previous['idle'])) {
            return null;
        }

        $totalDelta = $total - $previous['total'];
        $idleDelta = $idle - $previous['idle'];

        if ($totalDelta <= 0 || $idleDelta < 0) {
            return null;
        }

        return round(max(0, min(100, (1 - ($idleDelta / $totalDelta)) * 100)), 2);
    }

    /** @return array{?float, ?float, ?float} */
    private function loadAverages(): array
    {
        if (! $this->isLinux() || ! function_exists('sys_getloadavg')) {
            return [null, null, null];
        }

        $load = sys_getloadavg();

        if (! is_array($load)) {
            return [null, null, null];
        }

        $values = array_pad($load, 3, null);

        return array_map(
            static fn ($value): ?float => is_numeric($value) ? round((float) $value, 2) : null,
            array_slice($values, 0, 3),
        );
    }

    private function linuxCoreCount(): ?int
    {
        if (! $this->isLinux() || ! is_readable('/proc/cpuinfo')) {
            return null;
        }

        $contents = @file_get_contents('/proc/cpuinfo');

        if (! is_string($contents)) {
            return null;
        }

        $count = preg_match_all('/^processor\s*:/m', $contents);

        return is_int($count) && $count > 0 ? $count : null;
    }

    private function hostname(): string
    {
        if (function_exists('gethostname')) {
            $hostname = gethostname();

            if (is_string($hostname) && $hostname !== '') {
                return $hostname;
            }
        }

        return function_exists('php_uname') ? php_uname('n') : 'unknown-host';
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    private function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }
}
