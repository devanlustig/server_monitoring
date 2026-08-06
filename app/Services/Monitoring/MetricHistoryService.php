<?php

namespace App\Services\Monitoring;

use App\Models\MetricHistory;
use App\Models\MonitoredServer;
use App\Services\Monitoring\DTO\MetricSnapshotData;

class MetricHistoryService
{
    /**
     * Simpan snapshot metric ke database.
     *
     * @param MonitoredServer $server
     * @param MetricSnapshotData[] $snapshots
     * @return int Jumlah metric yang berhasil disimpan
     */
    public function store(
        MonitoredServer $server,
        array $snapshots
    ): int {

        if (empty($snapshots)) {
            return 0;
        }

        $records = [];
        $createdAt = now();

        foreach ($snapshots as $snapshot) {

            if (! $snapshot instanceof MetricSnapshotData) {
                continue;
            }

            $records[] = [

                'monitored_server_id' => $server->id,
                'category' => $snapshot->category,
                'metric_name' => $snapshot->metricName,
                'metric_value' => $snapshot->metricValue,
                'metric_unit' => $snapshot->metricUnit,
                'snapshot_at' => $snapshot->snapshotAt,
                'created_at' => $createdAt,

            ];
        }

        if (empty($records)) {
            return 0;
        }

        collect($records)
            ->chunk(500)
            ->each(function ($chunk) {
                MetricHistory::insert(
                    $chunk->toArray()
                );
            });

        return count($records);
    }
}