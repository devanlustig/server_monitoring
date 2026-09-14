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
use Illuminate\Support\Facades\DB;
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

        // 1. Lightweight lookup for latest target snapshot timestamp on target date
        $latestTargetAt = DB::table('disk_directory_snapshots')
            ->where('server_id', $server->id)
            ->whereDate('snapshot_at', $targetDate)
            ->max('snapshot_at');

        if (!$latestTargetAt) {
            return [
                'date' => $targetDate,
                'dateFormatted' => $dateFormatted,
                'totalGrowthBytes' => $totalGrowthBytes,
                'totalGrowthFormatted' => $this->growthService->formatGrowth($totalGrowthBytes),
                'directories' => [],
            ];
        }

        // 2. Lightweight lookup for preceding date snapshot timestamp
        $latestTargetCarbon = Carbon::parse($latestTargetAt);
        $prevSnapshotAt = DB::table('disk_directory_snapshots')
            ->where('server_id', $server->id)
            ->where('snapshot_at', '<', $latestTargetCarbon->copy()->startOfDay())
            ->max('snapshot_at');

        if (!$prevSnapshotAt) {
            $prevSnapshotAt = DB::table('disk_directory_snapshots')
                ->where('server_id', $server->id)
                ->where('snapshot_at', '<', $latestTargetAt)
                ->max('snapshot_at');
        }

        // 3. Database JOIN for ONLY the single latest snapshot timestamp vs previous snapshot timestamp
        $query = DB::table('disk_directory_snapshots as cur')
            ->where('cur.server_id', $server->id)
            ->where('cur.snapshot_at', $latestTargetAt);

        if ($prevSnapshotAt) {
            $query->leftJoin('disk_directory_snapshots as prev', function ($join) use ($server, $prevSnapshotAt) {
                $join->on('prev.path', '=', 'cur.path')
                     ->where('prev.server_id', '=', $server->id)
                     ->where('prev.snapshot_at', '=', $prevSnapshotAt);
            });

            $prevSizeSql = "CASE WHEN prev.size_bytes IS NOT NULL THEN prev.size_bytes ELSE NULL END";
            $growthSql = "CASE WHEN prev.size_bytes IS NOT NULL THEN (cur.size_bytes - prev.size_bytes) ELSE NULL END";
        } else {
            $prevSizeSql = "NULL";
            $growthSql = "NULL";
        }

        $rawDirs = $query->select([
            'cur.path',
            'cur.size_bytes as current_size_bytes',
            DB::raw("{$prevSizeSql} as previous_size_bytes"),
            DB::raw("{$growthSql} as growth_bytes"),
        ])->get();

        // 4. Filter non-overlapping directories to prevent double-counting
        $allPaths = $rawDirs->pluck('path')->toArray();
        $leafPathsSet = array_flip($this->filterNonOverlappingPaths($allPaths));

        $directoryResults = [];
        foreach ($rawDirs as $row) {
            if (!isset($leafPathsSet[$row->path])) {
                continue;
            }

            $currentSize = (int) $row->current_size_bytes;
            $previousSize = $row->previous_size_bytes !== null ? (int) $row->previous_size_bytes : null;
            $growth = $row->growth_bytes !== null ? (int) $row->growth_bytes : null;

            $previousSizeFormatted = $previousSize !== null ? $this->formatBytes($previousSize) : 'N/A';
            $growthFormatted = $growth !== null ? $this->growthService->formatGrowth($growth) : 'N/A';

            $directoryResults[] = new StorageGrowthDirectoryData(
                path: $row->path,
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
                if ($a->growthBytes === $b->growthBytes) {
                    return $b->currentSizeBytes <=> $a->currentSizeBytes;
                }
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
    /**
     * Get file storage growth contributors in a specific directory using database-side aggregation.
     */
    public function getFileGrowth(MonitoredServer $server, string $date, string $directory): array
    {
        $directory = $this->sanitizeDirectoryPath($directory);
        $targetDate = Carbon::parse($date)->format('Y-m-d');
        $dirPrefix = rtrim($directory, '/') . '/';

        // 1. Lightweight lookup for latest target snapshot timestamp
        $latestTargetAt = DB::table('disk_file_snapshots')
            ->where('server_id', $server->id)
            ->whereDate('snapshot_at', $targetDate)
            ->max('snapshot_at');

        // Fallback: If no stored file snapshot, execute SSH live command
        if (!$latestTargetAt) {
            $targetFilesMap = $this->fetchLiveFilesForDirectory($server, $directory);
            return $this->formatLiveFilesResult($server, $directory, $targetDate, $targetFilesMap);
        }

        // 2. Lightweight lookup for previous snapshot timestamp
        $latestTargetCarbon = Carbon::parse($latestTargetAt);
        $prevSnapshotAt = DB::table('disk_file_snapshots')
            ->where('server_id', $server->id)
            ->where('snapshot_at', '<', $latestTargetCarbon->copy()->startOfDay())
            ->max('snapshot_at');

        if (!$prevSnapshotAt) {
            $prevSnapshotAt = DB::table('disk_file_snapshots')
                ->where('server_id', $server->id)
                ->where('snapshot_at', '<', $latestTargetAt)
                ->max('snapshot_at');
        }

        $hasPrev = $prevSnapshotAt ? 1 : 0;
        $maxPrevDepth = 0;

        if ($prevSnapshotAt) {
            $maxPrevDepthVal = DB::table('disk_file_snapshots')
                ->where('server_id', $server->id)
                ->where('snapshot_at', $prevSnapshotAt)
                ->where(function ($q) use ($directory, $dirPrefix) {
                    $q->where('file_path', 'like', $dirPrefix . '%')
                      ->orWhere('file_path', '=', $directory)
                      ->orWhere('directory_path', '=', $directory)
                      ->orWhere('directory_path', 'like', $dirPrefix . '%');
                })
                ->selectRaw("MAX(LENGTH(file_path) - LENGTH(REPLACE(file_path, '/', ''))) as max_depth")
                ->value('max_depth');

            $maxPrevDepth = $maxPrevDepthVal !== null ? (int) $maxPrevDepthVal : 0;
        }

        // 3. Database JOIN & CASE computation: returns max 15 rows directly from database
        $query = DB::table('disk_file_snapshots as cur')
            ->where('cur.server_id', $server->id)
            ->where('cur.snapshot_at', $latestTargetAt)
            ->where(function ($q) use ($directory, $dirPrefix) {
                $q->where('cur.file_path', 'like', $dirPrefix . '%')
                  ->orWhere('cur.file_path', '=', $directory)
                  ->orWhere('cur.directory_path', '=', $directory)
                  ->orWhere('cur.directory_path', 'like', $dirPrefix . '%');
            });

        if ($prevSnapshotAt) {
            $query->leftJoin('disk_file_snapshots as prev', function ($join) use ($server, $prevSnapshotAt) {
                $join->on('prev.file_path', '=', 'cur.file_path')
                     ->where('prev.server_id', '=', $server->id)
                     ->where('prev.snapshot_at', '=', $prevSnapshotAt);
            });

            $prevSizeSql = "CASE
                WHEN prev.size_bytes IS NOT NULL THEN prev.size_bytes
                WHEN {$maxPrevDepth} > 0 AND (LENGTH(cur.file_path) - LENGTH(REPLACE(cur.file_path, '/', ''))) <= {$maxPrevDepth} THEN 0
                ELSE NULL
            END";

            $growthSql = "CASE
                WHEN prev.size_bytes IS NOT NULL THEN (cur.size_bytes - prev.size_bytes)
                WHEN {$maxPrevDepth} > 0 AND (LENGTH(cur.file_path) - LENGTH(REPLACE(cur.file_path, '/', ''))) <= {$maxPrevDepth} THEN cur.size_bytes
                ELSE NULL
            END";
        } else {
            $prevSizeSql = "NULL";
            $growthSql = "NULL";
        }

        $rows = $query->select([
                'cur.file_path',
                'cur.size_bytes as current_size_bytes',
                DB::raw("{$prevSizeSql} as previous_size_bytes"),
                DB::raw("{$growthSql} as growth_bytes"),
            ])
            ->orderByRaw("CASE WHEN ({$growthSql}) IS NOT NULL THEN 0 ELSE 1 END ASC")
            ->orderByRaw("({$growthSql}) DESC")
            ->orderBy('cur.size_bytes', 'desc')
            ->limit(15)
            ->get();

        $filePaths = $rows->pluck('file_path')->toArray();
        $oidMap = $this->fetchPostgresDatabaseOidMap($server, $filePaths);

        $fileResults = [];
        foreach ($rows as $row) {
            $currentSize = (int) $row->current_size_bytes;
            $previousSize = $row->previous_size_bytes !== null ? (int) $row->previous_size_bytes : null;
            $growth = $row->growth_bytes !== null ? (int) $row->growth_bytes : null;

            $previousSizeFormatted = $previousSize !== null ? $this->formatBytes($previousSize) : 'N/A';
            $growthFormatted = $growth !== null ? $this->growthService->formatGrowth($growth) : 'N/A';
            $databaseName = $this->extractPostgresDatabaseName($row->file_path, $oidMap);

            $fileResults[] = new StorageGrowthFileData(
                path: $row->file_path,
                filename: basename($row->file_path),
                currentSizeBytes: $currentSize,
                previousSizeBytes: $previousSize,
                growthBytes: $growth,
                currentSizeFormatted: $this->formatBytes($currentSize),
                previousSizeFormatted: $previousSizeFormatted,
                growthFormatted: $growthFormatted,
                databaseName: $databaseName
            );
        }

        return [
            'directory' => $directory,
            'date' => $targetDate,
            'files' => array_map(fn($f) => $f->toArray(), $fileResults),
        ];
    }

    private function formatLiveFilesResult(MonitoredServer $server, string $directory, string $targetDate, \Illuminate\Support\Collection $targetFilesMap): array
    {
        $filePaths = $targetFilesMap->keys()->take(15)->toArray();
        $oidMap = $this->fetchPostgresDatabaseOidMap($server, $filePaths);

        $fileResults = [];
        foreach ($targetFilesMap->take(15) as $filePath => $item) {
            $currentSize = is_object($item) && isset($item->size_bytes) ? (int) $item->size_bytes : (int) $item;
            $databaseName = $this->extractPostgresDatabaseName($filePath, $oidMap);
            $fileResults[] = new StorageGrowthFileData(
                path: $filePath,
                filename: basename($filePath),
                currentSizeBytes: $currentSize,
                previousSizeBytes: null,
                growthBytes: null,
                currentSizeFormatted: $this->formatBytes($currentSize),
                previousSizeFormatted: 'N/A',
                growthFormatted: 'N/A',
                databaseName: $databaseName
            );
        }
        return [
            'directory' => $directory,
            'date' => $targetDate,
            'files' => array_map(fn($f) => $f->toArray(), $fileResults),
        ];
    }

    /**
     * Extract PostgreSQL database OIDs from a list of file paths and fetch DB names via SSH.
     *
     * @param MonitoredServer $server
     * @param array $filePaths
     * @return array Map of [oid_string => database_name]
     */
    public function fetchPostgresDatabaseOidMap(MonitoredServer $server, array $filePaths): array
    {
        $oids = [];
        foreach ($filePaths as $path) {
            if (preg_match('/\/var\/lib\/postgresql\/(?:[^\/]+\/)+base\/(\d+)(?:\/|$)/', $path, $m)) {
                $oids[$m[1]] = true;
            }
        }

        if (empty($oids)) {
            return [];
        }

        $oidMap = [];
        try {
            $cmd = "sudo -u postgres psql -t -A -F ',' -c \"SELECT oid, datname FROM pg_database;\" 2>/dev/null";
            $result = $this->commands->execute($server, $cmd);

            if ($result->successful && !empty($result->output)) {
                $lines = explode("\n", trim($result->output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^(\d+),(.*)$/', $line, $m)) {
                        $oidMap[$m[1]] = trim($m[2]);
                    }
                }
            }
        } catch (Exception $e) {
            logger()->warning("Failed to fetch PostgreSQL database OID map: " . $e->getMessage());
        }

        return $oidMap;
    }

    /**
     * Extract database name for a PostgreSQL file path if OID match exists.
     */
    public function extractPostgresDatabaseName(string $path, array $oidMap): ?string
    {
        if (preg_match('/\/var\/lib\/postgresql\/(?:[^\/]+\/)+base\/(\d+)(?:\/|$)/', $path, $m)) {
            $oid = $m[1];
            return $oidMap[$oid] ?? 'Unknown';
        }

        return null;
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
