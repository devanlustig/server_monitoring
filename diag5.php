<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$server = \App\Models\MonitoredServer::find(1);
$service = new \App\Services\Monitoring\DiskStorageGrowthService();
$result = $service->getDailyGrowth($server, 5);

foreach ($result as $row) {
    echo $row->dateFormatted . "    " . $row->growthFormatted . " (" . $row->growthBytes . ")\n";
}

// Find directories for 11 Sep where growth was -3.6 GB
$dirService = $app->make(\App\Services\Monitoring\DiskGrowthDetailService::class);
$dirs = $dirService->getDirectoryGrowth($server, '2026-09-11');
echo "\nTop 10 returned by service:\n";
foreach ($dirs['directories'] as $d) {
    echo $d['path'] . "   " . $d['growthFormatted'] . " (" . $d['growthBytes'] . " bytes)\n";
}

// Manually fetch all directories with their growth for 11 Sep
echo "\nDirectories with negative growth on 11 Sep:\n";
$latestTargetAt = \Illuminate\Support\Facades\DB::table('disk_directory_snapshots')
    ->where('server_id', 1)
    ->whereDate('snapshot_at', '2026-09-11')
    ->max('snapshot_at');

$prevSnapshotAt = \Illuminate\Support\Facades\DB::table('disk_directory_snapshots')
    ->where('server_id', 1)
    ->where('snapshot_at', '<', \Carbon\Carbon::parse($latestTargetAt)->startOfDay())
    ->max('snapshot_at');

$query = \Illuminate\Support\Facades\DB::table('disk_directory_snapshots as cur')
    ->where('cur.server_id', 1)
    ->where('cur.snapshot_at', $latestTargetAt)
    ->leftJoin('disk_directory_snapshots as prev', function ($join) use ($prevSnapshotAt) {
        $join->on('prev.path', '=', 'cur.path')
             ->where('prev.server_id', '=', 1)
             ->where('prev.snapshot_at', '=', $prevSnapshotAt);
    })
    ->select([
        'cur.path',
        'prev.size_bytes as prev_size',
        'cur.size_bytes as cur_size',
        \Illuminate\Support\Facades\DB::raw('(cur.size_bytes - prev.size_bytes) as growth_bytes')
    ])
    ->whereNotNull('prev.size_bytes')
    ->orderBy('growth_bytes', 'asc')
    ->limit(10)
    ->get();

foreach ($query as $row) {
    if ($row->growth_bytes < 0) {
        $prevGB = number_format($row->prev_size / 1073741824, 2);
        $curGB = number_format($row->cur_size / 1073741824, 2);
        $growthGB = number_format($row->growth_bytes / 1073741824, 2);
        echo "{$row->path}   {$prevGB} GB   {$curGB} GB   {$growthGB} GB ({$row->growth_bytes})\n";
    }
}
