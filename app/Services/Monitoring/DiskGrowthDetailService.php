<?php

namespace App\Services\Monitoring;

use App\Models\DiskDirectorySnapshot;
use App\Models\DiskFileSnapshot;
use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\StorageGrowthDirectoryData;
use App\Services\Monitoring\DTO\StorageGrowthFileData;
use App\Services\Monitoring\RemoteCommandService;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;

class DiskGrowthDetailService
{
    public function __construct(
        private readonly DiskStorageGrowthService $growthService,
        private readonly RemoteCommandService $commands
    ) {}

    /**
     * Get directory storage growth contributors for a server on a specific date.
     */
    public function getDirectoryGrowth(MonitoredServer $server, string $date): array
    {
        $targetDate = Carbon::parse($date)->format('Y-m-d');

        // Find total disk growth for target date using DiskStorageGrowthService
        $dailyGrowths = $this->growthService->getDailyGrowth($server, 10);
        $totalGrowthBytes = 0;
        $dateFormatted = Carbon::parse($targetDate)->format('d M Y');

        foreach ($dailyGrowths as $dg) {
            if ($dg->date === $targetDate) {
                $totalGrowthBytes = $dg->growthBytes;
                $dateFormatted = $dg->dateFormatted;
                break;
            }
        }

        // Get directory snapshots for target date
        $targetSnapshots = DiskDirectorySnapshot::where('server_id', $server->id)
            ->whereDate('snapshot_at', $targetDate)
            ->orderBy('snapshot_at', 'desc')
            ->get();

        if ($targetSnapshots->isEmpty()) {
            return [
                'date' => $targetDate,
                'dateFormatted' => $dateFormatted,
                'totalGrowthBytes' => $totalGrowthBytes,
                'totalGrowthFormatted' => $this->growthService->formatGrowth($totalGrowthBytes),
                'directories' => [],
            ];
        }

        // Latest snapshot_at timestamp on target date
        $latestTargetAt = $targetSnapshots->first()->snapshot_at;
        $targetDirs = $targetSnapshots->where('snapshot_at', $latestTargetAt)->keyBy('path');

        // Find preceding date snapshot timestamp
        $prevSnapshotAt = DiskDirectorySnapshot::where('server_id', $server->id)
            ->where('snapshot_at', '<', $latestTargetAt->copy()->startOfDay())
            ->orderBy('snapshot_at', 'desc')
            ->value('snapshot_at');

        $prevDirs = collect();
        if ($prevSnapshotAt) {
            $prevDirs = DiskDirectorySnapshot::where('server_id', $server->id)
                ->where('snapshot_at', $prevSnapshotAt)
                ->get()
                ->keyBy('path');
        }

        // Filter non-overlapping directories to prevent double-counting
        $allPaths = $targetDirs->keys()->toArray();
        $leafPaths = $this->filterNonOverlappingPaths($allPaths);

        $directoryResults = [];
        $sumDirectoriesGrowth = 0;

        foreach ($leafPaths as $path) {
            $currentSize = (int) ($targetDirs->get($path)?->size_bytes ?? 0);
            
            if ($prevSnapshotAt && $prevDirs->has($path)) {
                $previousSize = (int) $prevDirs->get($path)->size_bytes;
                $growth = $currentSize - $previousSize;
                $previousSizeFormatted = $this->formatBytes($previousSize);
                $growthFormatted = $this->growthService->formatGrowth($growth);
                $sumDirectoriesGrowth += max(0, $growth);
            } else {
                $previousSize = null;
                $growth = null;
                $previousSizeFormatted = 'N/A';
                $growthFormatted = 'N/A';
            }

            $directoryResults[] = new StorageGrowthDirectoryData(
                path: $path,
                currentSizeBytes: $currentSize,
                previousSizeBytes: $previousSize,
                growthBytes: $growth,
                currentSizeFormatted: $this->formatBytes($currentSize),
                previousSizeFormatted: $previousSizeFormatted,
                growthFormatted: $growthFormatted
            );
        }

        // Sort by growthBytes DESC (put nulls at the end, sorted by currentSizeBytes)
        usort($directoryResults, function ($a, $b) {
            if ($a->growthBytes !== null && $b->growthBytes !== null) {
                return $b->growthBytes <=> $a->growthBytes;
            }
            if ($a->growthBytes !== null) return -1;
            if ($b->growthBytes !== null) return 1;
            return $b->currentSizeBytes <=> $a->currentSizeBytes;
        });

        // Limit to top 10 directories
        $topDirectories = array_slice($directoryResults, 0, 10);

        // Add 'Other' entry if totalGrowthBytes > 0 and sum of top directories < totalGrowthBytes
        $topSum = array_sum(array_map(fn($d) => max(0, $d->growthBytes ?? 0), $topDirectories));
        $otherGrowth = max(0, $totalGrowthBytes - $topSum);

        if ($otherGrowth > 0 && count($topDirectories) > 0) {
            $topDirectories[] = new StorageGrowthDirectoryData(
                path: 'Other (unclassified storage)',
                currentSizeBytes: 0,
                previousSizeBytes: 0,
                growthBytes: $otherGrowth,
                currentSizeFormatted: '-',
                previousSizeFormatted: '-',
                growthFormatted: $this->growthService->formatGrowth($otherGrowth)
            );
        }

        return [
            'date' => $targetDate,
            'dateFormatted' => $dateFormatted,
            'totalGrowthBytes' => $totalGrowthBytes,
            'totalGrowthFormatted' => $this->growthService->formatGrowth($totalGrowthBytes),
            'directories' => array_map(fn($d) => $d->toArray(), $topDirectories),
        ];
    }

    /**
     * Get file storage growth contributors in a specific directory.
     */
    /**
     * Get file storage growth contributors in a specific directory.
     */
    public function getFileGrowth(MonitoredServer $server, string $date, string $directory): array
    {
        $directory = $this->sanitizeDirectoryPath($directory);
        $targetDate = Carbon::parse($date)->format('Y-m-d');
        $dirPrefix = rtrim($directory, '/') . '/';

        // Fetch file snapshots for target date that belong to the subtree of $directory
        $targetSnapshots = DiskFileSnapshot::where('server_id', $server->id)
            ->whereDate('snapshot_at', $targetDate)
            ->where(function ($query) use ($directory, $dirPrefix) {
                $query->where('file_path', 'like', $dirPrefix . '%')
                      ->orWhere('file_path', '=', $directory)
                      ->orWhere('directory_path', '=', $directory)
                      ->orWhere('directory_path', 'like', $dirPrefix . '%');
            })
            ->orderBy('snapshot_at', 'desc')
            ->get();

        // If no file snapshot stored for this subtree, execute SSH command for selected directory
        if ($targetSnapshots->isEmpty()) {
            $targetFilesMap = $this->fetchLiveFilesForDirectory($server, $directory);
            $prevFilesMap = collect();
            $prevSnapshotAt = null;
        } else {
            $latestTargetAt = $targetSnapshots->first()->snapshot_at;
            $targetFilesMap = $targetSnapshots->where('snapshot_at', $latestTargetAt)->keyBy('file_path');

            // Find previous snapshot timestamp for this server prior to target date
            $prevSnapshotAt = DiskFileSnapshot::where('server_id', $server->id)
                ->where('snapshot_at', '<', $latestTargetAt->copy()->startOfDay())
                ->orderBy('snapshot_at', 'desc')
                ->value('snapshot_at');

            if (!$prevSnapshotAt) {
                $prevSnapshotAt = DiskFileSnapshot::where('server_id', $server->id)
                    ->where('snapshot_at', '<', $latestTargetAt)
                    ->orderBy('snapshot_at', 'desc')
                    ->value('snapshot_at');
            }

            $prevFilesMap = collect();
            if ($prevSnapshotAt) {
                $prevFilesMap = DiskFileSnapshot::where('server_id', $server->id)
                    ->where('snapshot_at', $prevSnapshotAt)
                    ->where(function ($query) use ($directory, $dirPrefix) {
                        $query->where('file_path', 'like', $dirPrefix . '%')
                              ->orWhere('file_path', '=', $directory)
                              ->orWhere('directory_path', '=', $directory)
                              ->orWhere('directory_path', 'like', $dirPrefix . '%');
                    })
                    ->get()
                    ->keyBy('file_path');
            }
        }

        $fileResults = [];

        foreach ($targetFilesMap as $filePath => $item) {
            $currentSize = is_object($item) && isset($item->size_bytes) ? (int) $item->size_bytes : (int) $item;
            
            if ($prevSnapshotAt !== null) {
                if ($prevFilesMap->has($filePath)) {
                    $prevItem = $prevFilesMap->get($filePath);
                    $previousSize = is_object($prevItem) && isset($prevItem->size_bytes) ? (int) $prevItem->size_bytes : 0;
                    $growth = $currentSize - $previousSize;
                    $previousSizeFormatted = $this->formatBytes($previousSize);
                    $growthFormatted = $this->growthService->formatGrowth($growth);
                } else {
                    if ($this->isSnapshotCoverageComparable($filePath, $prevFilesMap)) {
                        // Genuinely new file in a comparable snapshot coverage/depth
                        $previousSize = 0;
                        $growth = $currentSize;
                        $previousSizeFormatted = '0 B';
                        $growthFormatted = $this->growthService->formatGrowth($growth);
                    } else {
                        // File missing because previous snapshot coverage/depth was not comparable
                        $previousSize = null;
                        $growth = null;
                        $previousSizeFormatted = 'N/A';
                        $growthFormatted = 'N/A';
                    }
                }
            } else {
                // Previous snapshot is NOT available
                $previousSize = null;
                $growth = null;
                $previousSizeFormatted = 'N/A';
                $growthFormatted = 'N/A';
            }

            $fileResults[] = new StorageGrowthFileData(
                path: $filePath,
                filename: basename($filePath),
                currentSizeBytes: $currentSize,
                previousSizeBytes: $previousSize,
                growthBytes: $growth,
                currentSizeFormatted: $this->formatBytes($currentSize),
                previousSizeFormatted: $previousSizeFormatted,
                growthFormatted: $growthFormatted
            );
        }

        // Sort by growthBytes DESC; if growth is null ('N/A'), fallback to currentSizeBytes DESC
        usort($fileResults, function ($a, $b) {
            if ($a->growthBytes !== null && $b->growthBytes !== null) {
                if ($a->growthBytes === $b->growthBytes) {
                    return $b->currentSizeBytes <=> $a->currentSizeBytes;
                }
                return $b->growthBytes <=> $a->growthBytes;
            }
            if ($a->growthBytes !== null) return -1;
            if ($b->growthBytes !== null) return 1;
            return $b->currentSizeBytes <=> $a->currentSizeBytes;
        });

        // Limit to top 15 files
        $topFiles = array_slice($fileResults, 0, 15);

        return [
            'directory' => $directory,
            'date' => $targetDate,
            'files' => array_map(fn($f) => $f->toArray(), $topFiles),
        ];
    }

    /**
     * Filter directory paths to prevent double counting parent + child.
     */
    public function filterNonOverlappingPaths(array $paths): array
    {
        // Exclude system pseudo roots
        $excluded = ['/', '/proc', '/sys', '/dev', '/run', '/boot', '/snap'];
        $paths = array_filter($paths, fn($p) => !in_array($p, $excluded));

        // Prefer deeper subdirectories over top parent directories if subdirs exist
        $leafPaths = [];
        foreach ($paths as $path) {
            $isParent = false;
            foreach ($paths as $otherPath) {
                if ($path !== $otherPath && str_starts_with($otherPath, $path . '/')) {
                    $isParent = true;
                    break;
                }
            }
            if (!$isParent) {
                $leafPaths[] = $path;
            }
        }

        return array_values($leafPaths);
    }

    /**
     * Sanitize directory path for security (prevent injection / path traversal).
     */
    public function sanitizeDirectoryPath(string $directory): string
    {
        $directory = trim($directory);

        if (empty($directory) || !str_starts_with($directory, '/')) {
            throw new InvalidArgumentException("Directory path must start with '/'");
        }

        if (str_contains($directory, '..') || str_contains($directory, "\0")) {
            throw new InvalidArgumentException("Directory path cannot contain path traversal characters");
        }

        $forbidden = ['/proc', '/sys', '/dev', '/run'];
        foreach ($forbidden as $f) {
            if ($directory === $f || str_starts_with($directory, $f . '/')) {
                throw new InvalidArgumentException("Access to pseudo-filesystem directory '{$f}' is forbidden");
            }
        }

        return $directory;
    }

    /**
     * Fetch live file sizes for a directory via SSH as fallback.
     */
    private function fetchLiveFilesForDirectory(MonitoredServer $server, string $directory): \Illuminate\Support\Collection
    {
        $resultMap = collect();
        try {
            $depth = (str_contains($directory, 'postgresql') || str_contains($directory, 'mysql')) ? 8 : 6;
            $cmd = 'find ' . escapeshellarg($directory) . ' -maxdepth ' . $depth . ' -type f -printf "%s %p\n" 2>/dev/null | sort -rn | head -30';
            $result = $this->commands->execute($server, $cmd);

            if ($result->successful && !empty($result->output)) {
                $lines = explode("\n", trim($result->output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                        $bytes = (int) $m[1];
                        $filePath = trim($m[2]);
                        $resultMap->put($filePath, (object) ['size_bytes' => $bytes, 'file_path' => $filePath]);
                    }
                }
            }
        } catch (Exception $e) {
            logger()->warning("Live file fetch failed for {$directory}: " . $e->getMessage());
        }

        return $resultMap;
    }

    /**
     * Determine whether the previous snapshot had comparable depth/coverage for a file path.
     */
    public function isSnapshotCoverageComparable(string $filePath, \Illuminate\Support\Collection $prevFilesMap): bool
    {
        if ($prevFilesMap->isEmpty()) {
            return false;
        }

        $fileDepth = count(explode('/', trim($filePath, '/')));
        
        $maxPrevDepth = 0;
        $prevDirPaths = [];

        foreach ($prevFilesMap as $prevFile) {
            $path = is_object($prevFile) && isset($prevFile->file_path) ? $prevFile->file_path : (string) $prevFile;
            if ($path) {
                $depth = count(explode('/', trim($path, '/')));
                if ($depth > $maxPrevDepth) {
                    $maxPrevDepth = $depth;
                }
                $dir = is_object($prevFile) && isset($prevFile->directory_path) ? $prevFile->directory_path : dirname($path);
                $prevDirPaths[$dir] = true;
            }
        }

        if ($maxPrevDepth === 0) {
            return false;
        }

        // Target file path depth exceeds maximum depth captured in previous snapshot
        if ($fileDepth > $maxPrevDepth) {
            return false;
        }

        // Exact parent directory was covered in previous snapshot
        $targetDir = dirname($filePath);
        if (isset($prevDirPaths[$targetDir])) {
            return true;
        }

        // Check if any directory in previous snapshot shares the same parent directory depth
        $targetDirDepth = count(explode('/', trim($targetDir, '/')));
        foreach (array_keys($prevDirPaths) as $prevDir) {
            $prevDirDepth = count(explode('/', trim($prevDir, '/')));
            if ($prevDirDepth >= $targetDirDepth) {
                return true;
            }
        }

        return false;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
