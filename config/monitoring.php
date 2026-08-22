<?php

use App\Services\Monitoring\ApacheSnapshotProvider;

return [

    'providers' => [
        ApacheSnapshotProvider::class,
        \App\Services\Monitoring\NginxSnapshotProvider::class,
    ],
    'thresholds' => [
        'slow_request_ms' => 3000,
    ]

];