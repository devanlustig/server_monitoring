<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => ':memory:']);

\Illuminate\Support\Facades\Schema::create('disk_metrics', function ($table) {
    $table->id();
    $table->integer('server_id');
    $table->bigInteger('used')->nullable();
    $table->timestamp('collected_at');
});

\Illuminate\Support\Facades\DB::table('disk_metrics')->insert([
    ['server_id' => 1, 'used' => 100, 'collected_at' => '2026-09-25 10:00:00'],
    ['server_id' => 1, 'used' => 200, 'collected_at' => '2026-09-25 10:00:00']
]);

try {
    $res = \Illuminate\Support\Facades\DB::select("
        SELECT DISTINCT ON (DATE(collected_at)) DATE(collected_at) as date, used, collected_at
        FROM disk_metrics
        WHERE server_id = 1 AND used IS NOT NULL
        ORDER BY DATE(collected_at) DESC, collected_at DESC
        LIMIT 6;
    ");
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    $res = \Illuminate\Support\Facades\DB::select("
        SELECT DATE(collected_at) as date, used, collected_at
        FROM (
            SELECT *, ROW_NUMBER() OVER (PARTITION BY DATE(collected_at) ORDER BY collected_at DESC) as rn
            FROM disk_metrics
            WHERE server_id = 1 AND used IS NOT NULL
        ) t
        WHERE rn = 1
        ORDER BY date DESC
        LIMIT 6;
    ");
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
