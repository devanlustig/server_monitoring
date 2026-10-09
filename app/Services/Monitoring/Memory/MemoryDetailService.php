<?php

namespace App\Services\Monitoring\Memory;

use App\Models\MonitoredServer;
use App\Services\Monitoring\RemoteCommandService;
use Exception;
use Illuminate\Support\Facades\Log;

class MemoryDetailService
{
    public function __construct(
        private readonly RemoteCommandService $commands
    ) {}

    public function getMemoryDetail(MonitoredServer $server): array
    {
        $commands = [
            'free' => 'free -b',
            'meminfo' => 'cat /proc/meminfo',
            'ps' => 'ps -eo pid,comm,user,rss,pmem --sort=-rss | head -n 16',
        ];

        try {
            $batchResult = $this->commands->executeMany($server, $commands);
        } catch (Exception $e) {
            Log::error('SSH connection failed for Memory Detail', [
                'server_id' => $server->id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to connect to server: ' . $e->getMessage(),
            ];
        }

        $freeOutput = $batchResult->get('free');
        $meminfoOutput = $batchResult->get('meminfo');
        $psOutput = $batchResult->get('ps');

        if (!$freeOutput && !$meminfoOutput) {
            return [
                'success' => false,
                'message' => 'Unable to read memory information from server.',
            ];
        }

        $breakdown = $this->parseMemoryBreakdown($freeOutput, $meminfoOutput);
        $processes = $this->parseTopProcesses($psOutput);
        $analysis = $this->generateAnalysis($breakdown, $processes);

        return [
            'success' => true,
            'breakdown' => $breakdown,
            'processes' => $processes,
            'analysis' => $analysis,
        ];
    }

    private function parseMemoryBreakdown(?string $freeOutput, ?string $meminfoOutput): array
    {
        $breakdown = [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'shared' => 0,
            'cache' => 0,
            'buffers' => 0,
            'available' => 0,
            'swap_total' => 0,
            'swap_used' => 0,
            'swap_free' => 0,
        ];

        // Parse free -b
        if ($freeOutput) {
            $lines = preg_split('/\r?\n/', trim($freeOutput));
            foreach ($lines as $line) {
                if (str_starts_with($line, 'Mem:')) {
                    $values = preg_split('/\s+/', trim($line));
                    $breakdown['total'] = (int)($values[1] ?? 0);
                    $breakdown['used'] = (int)($values[2] ?? 0);
                    $breakdown['free'] = (int)($values[3] ?? 0);
                    $breakdown['shared'] = (int)($values[4] ?? 0);
                    // values[5] is usually buff/cache
                    $breakdown['available'] = (int)($values[6] ?? 0);
                }
                if (str_starts_with($line, 'Swap:')) {
                    $values = preg_split('/\s+/', trim($line));
                    $breakdown['swap_total'] = (int)($values[1] ?? 0);
                    $breakdown['swap_used'] = (int)($values[2] ?? 0);
                    $breakdown['swap_free'] = (int)($values[3] ?? 0);
                }
            }
        }

        // Parse /proc/meminfo to get split cache and buffers if possible
        if ($meminfoOutput) {
            $lines = preg_split('/\r?\n/', trim($meminfoOutput));
            foreach ($lines as $line) {
                if (preg_match('/^Buffers:\s+(\d+)\s+kB/i', $line, $matches)) {
                    $breakdown['buffers'] = (int)$matches[1] * 1024;
                }
                if (preg_match('/^Cached:\s+(\d+)\s+kB/i', $line, $matches)) {
                    $breakdown['cache'] = (int)$matches[1] * 1024;
                }
                // Fallback for missing 'free' data
                if (!$freeOutput) {
                    if (preg_match('/^MemTotal:\s+(\d+)\s+kB/i', $line, $matches)) {
                        $breakdown['total'] = (int)$matches[1] * 1024;
                    }
                    if (preg_match('/^MemFree:\s+(\d+)\s+kB/i', $line, $matches)) {
                        $breakdown['free'] = (int)$matches[1] * 1024;
                    }
                    if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/i', $line, $matches)) {
                        $breakdown['available'] = (int)$matches[1] * 1024;
                    }
                    if (preg_match('/^SwapTotal:\s+(\d+)\s+kB/i', $line, $matches)) {
                        $breakdown['swap_total'] = (int)$matches[1] * 1024;
                    }
                    if (preg_match('/^SwapFree:\s+(\d+)\s+kB/i', $line, $matches)) {
                        $breakdown['swap_free'] = (int)$matches[1] * 1024;
                    }
                }
            }
            if (!$freeOutput) {
                // Approximate used if free -b failed
                $breakdown['used'] = $breakdown['total'] - $breakdown['free'] - $breakdown['buffers'] - $breakdown['cache'];
                $breakdown['swap_used'] = $breakdown['swap_total'] - $breakdown['swap_free'];
            }
        }

        return $breakdown;
    }

    private function parseTopProcesses(?string $psOutput): array
    {
        if (!$psOutput) {
            return [];
        }

        $processes = [];
        $lines = preg_split('/\r?\n/', trim($psOutput));
        
        // Skip header if present
        $start = 0;
        if (count($lines) > 0 && stripos($lines[0], 'PID') !== false) {
            $start = 1;
        }

        for ($i = $start; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $parts = preg_split('/\s+/', $line, 5);
            if (count($parts) >= 5) {
                // PID, COMMAND, USER, RSS, %MEM
                $rssKb = (int)$parts[3];
                $processes[] = [
                    'pid' => $parts[0],
                    'name' => $parts[1],
                    'user' => $parts[2],
                    'rss_bytes' => $rssKb * 1024,
                    'mem_percent' => (float)$parts[4],
                ];
            }
        }
        
        // Sort explicitly by RSS descending just in case ps output order failed
        usort($processes, function ($a, $b) {
            return $b['rss_bytes'] <=> $a['rss_bytes'];
        });

        // Limit to 15
        return array_slice($processes, 0, 15);
    }

    private function generateAnalysis(array $breakdown, array $processes): array
    {
        $hasPressure = false;
        $pressureReason = '';
        
        if ($breakdown['total'] > 0) {
            $availablePercent = ($breakdown['available'] / $breakdown['total']) * 100;
            if ($availablePercent < 10) {
                $hasPressure = true;
                $pressureReason = 'Available RAM is extremely low (under 10%).';
            }
        }

        if ($breakdown['swap_total'] > 0) {
            $swapUsedPercent = ($breakdown['swap_used'] / $breakdown['swap_total']) * 100;
            if ($swapUsedPercent > 50) {
                $hasPressure = true;
                $pressureReason .= ' Server is heavily relying on swap memory (over 50%).';
            }
        }

        $topProcessNote = null;
        if (count($processes) > 0) {
            $top = $processes[0];
            $topProcessNote = "{$top['name']} is the highest consumer at " . round($top['mem_percent'], 1) . "% RAM.";
        }

        return [
            'has_pressure' => $hasPressure,
            'pressure_reason' => trim($pressureReason),
            'top_process_note' => $topProcessNote,
            'summary' => $hasPressure 
                ? 'Server is experiencing memory pressure. ' . trim($pressureReason) 
                : 'Memory levels appear normal. No immediate pressure detected.'
        ];
    }
}
