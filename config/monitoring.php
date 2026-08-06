<?php

use App\Services\Monitoring\ApacheSnapshotProvider;

return [

    'providers' => [
        ApacheSnapshotProvider::class,
    ],
    'thresholds' => [
        'slow_request_ms' => 3000,
    ]

];