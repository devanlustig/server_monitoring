<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$res = \Illuminate\Support\Facades\DB::select("
    SELECT server_id, DATE(collected_at) AS metric_date, COUNT(*) AS row_count, MIN(collected_at) AS first_snapshot, MAX(collected_at) AS last_snapshot
    FROM disk_metrics
    GROUP BY server_id, DATE(collected_at)
    ORDER BY metric_date DESC, server_id
    LIMIT 20;
");
print_r($res);

$res2 = \Illuminate\Support\Facades\DB::select("
    SELECT DATE(collected_at) as snapshot_date, MAX(collected_at) as max_collected_at 
    FROM disk_metrics 
    WHERE server_id = 1 
    GROUP BY DATE(collected_at)
    ORDER BY snapshot_date DESC
    LIMIT 10;
");
print_r($res2);

$res3 = \Illuminate\Support\Facades\DB::select("
    SELECT server_id, collected_at, used
    FROM disk_metrics
    WHERE server_id = 1 AND DATE(collected_at) = CURRENT_DATE
    ORDER BY collected_at DESC
    LIMIT 10;
");
print_r($res3);
