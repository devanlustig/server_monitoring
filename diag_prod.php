<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$res = \Illuminate\Support\Facades\DB::select("
    SELECT
        server_id,
        MAX(collected_at) AS latest_collected_at
    FROM disk_metrics
    GROUP BY server_id
    ORDER BY server_id;
");
print_r($res);
