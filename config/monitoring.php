<?php

use App\Services\Monitoring\ApacheSnapshotProvider;

return [

    'providers' => [
        ApacheSnapshotProvider::class,
        \App\Services\Monitoring\NginxSnapshotProvider::class,
        \App\Services\Monitoring\PostgreSqlSnapshotProvider::class,
        \App\Services\Monitoring\SystemProcessSnapshotProvider::class,
        \App\Services\Monitoring\DiskFilesystemSnapshotProvider::class,
        \App\Services\Monitoring\PostgreSqlDatabaseSizeSnapshotProvider::class,
    ],
    'thresholds' => [
        'slow_request_ms' => 3000,
    ]

];