<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Models\CpuMetric;
use App\Models\MetricHistory;
use App\Services\Monitoring\CpuCorrelationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CpuCorrelationEngineTest extends TestCase
{
    use RefreshDatabase;

    private MonitoredServer $server;
    private CpuCorrelationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = MonitoredServer::create([
            'name' => 'test-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'nginx',
            'is_active' => true,
            'postgres_port' => 5432
        ]);
        $this->engine = new CpuCorrelationEngine();
    }

    public function test_scenario_1_high_requests_app_correlation()
    {
        $timestamp = Carbon::now();

        // High CPU
        CpuMetric::create([
            'server_id' => $this->server->id,
            'usage_percent' => 90.0,
            'load_1' => 5.0,
            'load_5' => 4.0,
            'load_15' => 3.0,
            'collected_at' => $timestamp
        ]);

        // Snapshots indicating high request rates and web server CPU
        MetricHistory::create([
            'monitored_server_id' => $this->server->id,
            'category' => 'nginx',
            'metric_name' => 'requests_per_minute',
            'metric_value' => 300.0,
            'snapshot_at' => $timestamp
        ]);

        MetricHistory::create([
            'monitored_server_id' => $this->server->id,
            'category' => 'process_category_cpu',
            'metric_name' => 'web_server',
            'metric_value' => 75.0,
            'snapshot_at' => $timestamp
        ]);

        // Historical baselines (low)
        for ($i = 1; $i <= 10; $i++) {
            MetricHistory::create([
                'monitored_server_id' => $this->server->id,
                'category' => 'nginx',
                'metric_name' => 'requests_per_minute',
                'metric_value' => 50.0,
                'snapshot_at' => $timestamp->copy()->subMinutes($i * 5)
            ]);
            MetricHistory::create([
                'monitored_server_id' => $this->server->id,
                'category' => 'process_category_cpu',
                'metric_name' => 'web_server',
                'metric_value' => 5.0,
                'snapshot_at' => $timestamp->copy()->subMinutes($i * 5)
            ]);
        }

        $result = $this->engine->analyze($this->server, $timestamp->toDateTimeString());

        $this->assertEquals('Application Request', $result['summary']['primaryCause']);
        $this->assertContains($result['summary']['confidence'], ['Likely Cause', 'Strong Correlation']);
    }

    public function test_scenario_2_high_postgres_cpu_database_correlation()
    {
        $timestamp = Carbon::now();

        // High CPU
        CpuMetric::create([
            'server_id' => $this->server->id,
            'usage_percent' => 95.0,
            'load_1' => 6.0,
            'load_5' => 5.0,
            'load_15' => 4.0,
            'collected_at' => $timestamp
        ]);

        // Snapshots indicating high database queries and postgres CPU
        MetricHistory::create([
            'monitored_server_id' => $this->server->id,
            'category' => 'postgresql',
            'metric_name' => 'active_connections',
            'metric_value' => 50.0,
            'snapshot_at' => $timestamp
        ]);

        MetricHistory::create([
            'monitored_server_id' => $this->server->id,
            'category' => 'process_category_cpu',
            'metric_name' => 'database',
            'metric_value' => 80.0,
            'snapshot_at' => $timestamp
        ]);

        // Historical baselines (low)
        for ($i = 1; $i <= 10; $i++) {
            MetricHistory::create([
                'monitored_server_id' => $this->server->id,
                'category' => 'postgresql',
                'metric_name' => 'active_connections',
                'metric_value' => 2.0,
                'snapshot_at' => $timestamp->copy()->subMinutes($i * 5)
            ]);
            MetricHistory::create([
                'monitored_server_id' => $this->server->id,
                'category' => 'process_category_cpu',
                'metric_name' => 'database',
                'metric_value' => 2.0,
                'snapshot_at' => $timestamp->copy()->subMinutes($i * 5)
            ]);
        }

        $result = $this->engine->analyze($this->server, $timestamp->toDateTimeString());

        $this->assertEquals('Database', $result['summary']['primaryCause']);
        $this->assertContains($result['summary']['confidence'], ['Likely Cause', 'Strong Correlation']);
    }

    public function test_scenario_3_high_cron_cpu_cron_correlation()
    {
        $timestamp = Carbon::now();

        // High CPU
        CpuMetric::create([
            'server_id' => $this->server->id,
            'usage_percent' => 90.0,
            'load_1' => 5.0,
            'load_5' => 4.0,
            'load_15' => 3.0,
            'collected_at' => $timestamp
        ]);

        // Snapshots indicating active cron/scheduler task
        MetricHistory::create([
            'monitored_server_id' => $this->server->id,
            'category' => 'process_category_cpu',
            'metric_name' => 'cron_scheduler',
            'metric_value' => 75.0,
            'snapshot_at' => $timestamp
        ]);

        // Historical baselines (low/inactive)
        for ($i = 1; $i <= 10; $i++) {
            MetricHistory::create([
                'monitored_server_id' => $this->server->id,
                'category' => 'process_category_cpu',
                'metric_name' => 'cron_scheduler',
                'metric_value' => 0.0,
                'snapshot_at' => $timestamp->copy()->subMinutes($i * 5)
            ]);
        }

        $result = $this->engine->analyze($this->server, $timestamp->toDateTimeString());

        $this->assertEquals('Cron/Scheduler', $result['summary']['primaryCause']);
        $this->assertContains($result['summary']['confidence'], ['Likely Cause', 'Strong Correlation']);
    }
}
