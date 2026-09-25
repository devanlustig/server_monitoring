<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = new \App\Services\Monitoring\DiskStorageGrowthService();

$servers = [1, 3];
foreach ($servers as $id) {
    $server = \App\Models\MonitoredServer::find($id);
    if (!$server) {
        echo "Server $id not found\n";
        continue;
    }
    echo "SERVER $id:\n";
    $result = $service->getDailyGrowth($server, 5);
    foreach ($result as $row) {
        echo $row->dateFormatted . "    " . $row->growthFormatted . "\n";
    }
    echo "\n";
}
