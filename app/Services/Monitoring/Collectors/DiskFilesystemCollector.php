<?php

namespace App\Services\Monitoring\Collectors;

use App\Models\DiskDirectorySnapshot;
use App\Models\DiskFileSnapshot;
use App\Models\MonitoredServer;
use App\Services\Monitoring\RemoteCommandService;
use Exception;

class DiskFilesystemCollector
{
    public function __construct(
        private readonly RemoteCommandService $commands
    ) {}

    public function collect(MonitoredServer $server): void
    {
        $snapshotAt = now();

        // 1. Collect Directory Sizes
        try {
            $dirCmd = 'du -b --max-depth=2 /var /home /usr /opt /tmp /etc /root /srv /www 2>/dev/null';
            $dirResult = $this->commands->execute($server, $dirCmd);

            if ($dirResult->successful && !empty($dirResult->output)) {
                $lines = explode("\n", trim($dirResult->output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                        $bytes = (int) $m[1];
                        $path = trim($m[2]);

                        DiskDirectorySnapshot::create([
                            'server_id' => $server->id,
                            'path' => $path,
                            'size_bytes' => $bytes,
                            'snapshot_at' => $snapshotAt,
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            logger()->warning("DiskFilesystemCollector directory collection failed for server {$server->name}: " . $e->getMessage());
        }

        // 2. Collect Top File Sizes
        try {
            $fileCmd = '(find /var/lib/postgresql /var/lib/mysql -maxdepth 8 -type f -printf "%s %p\n" 2>/dev/null; find /var/lib /var/log /var/www /home /tmp /opt /srv -maxdepth 5 -type f -printf "%s %p\n" 2>/dev/null) | sort -rn | head -150';
            $fileResult = $this->commands->execute($server, $fileCmd);

            if ($fileResult->successful && !empty($fileResult->output)) {
                $lines = explode("\n", trim($fileResult->output));
                $seenFiles = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                        $bytes = (int) $m[1];
                        $filePath = trim($m[2]);

                        if (isset($seenFiles[$filePath])) {
                            continue;
                        }
                        $seenFiles[$filePath] = true;

                        $dirPath = dirname($filePath);

                        DiskFileSnapshot::create([
                            'server_id' => $server->id,
                            'directory_path' => $dirPath,
                            'file_path' => $filePath,
                            'size_bytes' => $bytes,
                            'snapshot_at' => $snapshotAt,
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            logger()->warning("DiskFilesystemCollector file collection failed for server {$server->name}: " . $e->getMessage());
        }
    }
}
