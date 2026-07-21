<?php

namespace App\Services\Monitoring;

use App\Models\CpuMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

class CpuMonitorService
{
    /** Collect and persist a CPU sample for the host running this application. */
    public function collect(): CpuMetric
    {
        [$load1, $load5, $load15] = $this->loadAverages();

        return CpuMetric::query()->create([
            'hostname' => gethostname() ?: php_uname('n'),
            'usage_percent' => $this->usagePercent(),
            'load_1' => $load1,
            'load_5' => $load5,
            'load_15' => $load15,
            'core_count' => $this->coreCount(),
            'collected_at' => now(),
        ]);
    }

    private function usagePercent(): ?float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $result = Process::timeout(5)->run(
                'powershell.exe -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average"'
            );

            return $result->successful() && is_numeric(trim($result->output()))
                ? round((float) trim($result->output()), 2)
                : null;
        }

        $line = @file('/proc/stat')[0] ?? null;

        if ($line === null || ! str_starts_with($line, 'cpu ')) {
            return null;
        }

        $values = array_map('intval', preg_split('/\s+/', trim(substr($line, 4))));
        $total = array_sum($values);
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        $previous = Cache::pull('monitoring.cpu.previous');
        Cache::put('monitoring.cpu.previous', compact('total', 'idle'), now()->addMinutes(5));

        if (! is_array($previous) || $total <= $previous['total']) {
            return null;
        }

        return round((1 - (($idle - $previous['idle']) / ($total - $previous['total']))) * 100, 2);
    }

    /** @return array{?float, ?float, ?float} */
    private function loadAverages(): array
    {
        $load = sys_getloadavg();

        return is_array($load) ? array_map(static fn ($value) => round((float) $value, 2), $load) : [null, null, null];
    }

    private function coreCount(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $result = Process::timeout(5)->run(
                'powershell.exe -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum"'
            );

            return $result->successful() && ctype_digit(trim($result->output()))
                ? (int) trim($result->output())
                : null;
        }

        return is_file('/proc/cpuinfo')
            ? max(1, substr_count((string) file_get_contents('/proc/cpuinfo'), 'processor\t:'))
            : null;
    }
}
