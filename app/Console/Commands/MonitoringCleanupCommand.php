<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitoringCleanupCommand extends Command
{
    protected $signature = 'monitoring:cleanup
                            {--dry-run : Hanya menampilkan jumlah data tanpa menghapus}
                            {--batch=10000 : Jumlah row yang dihapus per batch}';

    protected $description = 'Membersihkan data monitoring yang sudah melewati retention period';

    /**
     * Retention:
     * - Metric utama: 30 hari
     * - Filesystem snapshots: 14 hari
     */
    private const RETENTION = [
        'cpu_metrics' => [
            'column' => 'collected_at',
            'days' => 20,
        ],
        'memory_metrics' => [
            'column' => 'collected_at',
            'days' => 20,
        ],
        'disk_metrics' => [
            'column' => 'collected_at',
            'days' => 20,
        ],
        'metric_histories' => [
            'column' => 'snapshot_at',
            'days' => 20,
        ],
        'application_request_logs' => [
            'column' => 'created_at',
            'days' => 20,
        ],
        'disk_directory_snapshots' => [
            'column' => 'snapshot_at',
            'days' => 12,
        ],
        'disk_file_snapshots' => [
            'column' => 'snapshot_at',
            'days' => 12,
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch'));

        $this->info('Monitoring cleanup started.');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - tidak ada data yang akan dihapus.');
        } else {
            $this->warn("Delete mode - batch size: {$batchSize}");
        }

        $totalDeleted = 0;

        foreach (self::RETENTION as $table => $config) {
            $column = $config['column'];
            $days = $config['days'];

            $cutoff = now()->subDays($days);

            $query = DB::table($table)
                ->where($column, '<', $cutoff);

            $count = (clone $query)->count();

            $this->line(
                sprintf(
                    '%-20s %8d rows older than %d days',
                    $table,
                    $count,
                    $days
                )
            );

            if ($dryRun || $count === 0) {
                continue;
            }

            $deleted = 0;

            while (true) {
                $ids = DB::table($table)
                    ->where($column, '<', $cutoff)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $affected = DB::table($table)
                    ->whereIn('id', $ids)
                    ->delete();

                $deleted += $affected;
                $totalDeleted += $affected;

                $this->line(
                    "  {$table}: deleted {$affected} rows"
                );
            }

            $this->info(
                "  {$table}: total deleted {$deleted} rows"
            );
        }

        $this->newLine();
        $this->info(
            $dryRun
                ? 'Dry run completed. No data was deleted.'
                : "Cleanup completed. Total deleted: {$totalDeleted} rows."
        );

        return self::SUCCESS;
    }
}