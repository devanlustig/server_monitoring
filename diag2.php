<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$res = \Illuminate\Support\Facades\DB::select("
    SELECT DISTINCT ON (DATE(collected_at)) DATE(collected_at) as date, used, collected_at
    FROM disk_metrics
    WHERE server_id = 1 AND used IS NOT NULL
    ORDER BY DATE(collected_at) DESC, collected_at DESC
    LIMIT 6;
");
print_r($res);
