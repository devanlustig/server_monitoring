<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Models\DiskMetric;
use App\Services\Monitoring\DiskStorageGrowthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DiskStorageGrowthServiceTest extends TestCase
{
    use RefreshDatabase;

    private MonitoredServer $server;
    private DiskStorageGrowthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = MonitoredServer::create([
            'name' => 'disk-test-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'nginx',
            'is_active' => true,
        ]);
        $this->service = new DiskStorageGrowthService();
    }

    public function test_scenario_1_five_normal_days_returns_max_5_rows()
    {
        $baseDate = Carbon::parse('2026-09-10 12:00:00');

        // Create metrics for 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = $baseDate->copy()->subDays($i);
            DiskMetric::create([
                'server_id' => $this->server->id,
                'hostname' => 'mutif',
                'total' => 200000000000,
                'used' => 100000000000 + ((6 - $i) * 1000000000), // Increasing used storage
                'available' => 100000000000,
                'usage_percent' => 50.0,
                'collected_at' => $date,
            ]);
        }

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(5, $result);
        $this->assertEquals('2026-09-10', $result[0]->date);
        $this->assertEquals('2026-09-06', $result[4]->date);
    }

    public function test_scenario_2_storage_increases_produces_positive_growth()
    {
        $day1 = Carbon::parse('2026-09-09 10:00:00');
        $day2 = Carbon::parse('2026-09-10 10:00:00');

        // Day 1: 53.8 GB used (57766862848 bytes)
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 57766862848,
            'available' => 42233137152,
            'usage_percent' => 57.7,
            'collected_at' => $day1,
        ]);

        // Day 2: 55.0 GB used (59055800320 bytes) -> Increase of ~1.2 GB
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 59055800320,
            'available' => 40944199680,
            'usage_percent' => 59.0,
            'collected_at' => $day2,
        ]);

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(2, $result);
        $this->assertEquals('2026-09-10', $result[0]->date);
        $this->assertGreaterThan(0, $result[0]->growthBytes);
        $this->assertStringStartsWith('+', $result[0]->growthFormatted);
    }

    public function test_scenario_3_storage_decreases_produces_negative_growth()
    {
        $day1 = Carbon::parse('2026-09-09 10:00:00');
        $day2 = Carbon::parse('2026-09-10 10:00:00');

        // Day 1: 55.0 GB used
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 59055800320,
            'available' => 40944199680,
            'usage_percent' => 59.0,
            'collected_at' => $day1,
        ]);

        // Day 2: 53.5 GB used -> Decrease of ~1.5 GB
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 57444741120,
            'available' => 42555258880,
            'usage_percent' => 57.4,
            'collected_at' => $day2,
        ]);

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(2, $result);
        $this->assertEquals('2026-09-10', $result[0]->date);
        $this->assertLessThan(0, $result[0]->growthBytes);
        $this->assertStringStartsWith('-', $result[0]->growthFormatted);
    }

    public function test_scenario_4_storage_unchanged_produces_zero_growth()
    {
        $day1 = Carbon::parse('2026-09-09 10:00:00');
        $day2 = Carbon::parse('2026-09-10 10:00:00');

        $used = 50000000000;

        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => $used,
            'available' => 50000000000,
            'usage_percent' => 50.0,
            'collected_at' => $day1,
        ]);

        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => $used,
            'available' => 50000000000,
            'usage_percent' => 50.0,
            'collected_at' => $day2,
        ]);

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(2, $result);
        $this->assertEquals(0, $result[0]->growthBytes);
        $this->assertEquals('0 GB', $result[0]->growthFormatted);
    }

    public function test_scenario_5_duplicate_snapshots_on_same_day_uses_latest_snapshot()
    {
        $day1 = Carbon::parse('2026-09-09 10:00:00');
        $day2_morning = Carbon::parse('2026-09-10 08:00:00');
        $day2_evening = Carbon::parse('2026-09-10 20:00:00');

        // Day 1
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 50000000000,
            'available' => 50000000000,
            'usage_percent' => 50.0,
            'collected_at' => $day1,
        ]);

        // Day 2 Morning (52 GB)
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 52000000000,
            'available' => 48000000000,
            'usage_percent' => 52.0,
            'collected_at' => $day2_morning,
        ]);

        // Day 2 Evening (55 GB - latest)
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 55000000000,
            'available' => 45000000000,
            'usage_percent' => 55.0,
            'collected_at' => $day2_evening,
        ]);

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(2, $result);
        $this->assertEquals('2026-09-10', $result[0]->date);
        // Growth should be based on latest (55 GB - 50 GB = 5 GB)
        $this->assertEquals(5000000000, $result[0]->growthBytes);
    }

    public function test_scenario_6_gap_day_does_not_create_dummy_rows()
    {
        $day1 = Carbon::parse('2026-09-05 10:00:00');
        $day2 = Carbon::parse('2026-09-08 10:00:00'); // Gap between Sep 05 and Sep 08
        $day3 = Carbon::parse('2026-09-10 10:00:00'); // Gap between Sep 08 and Sep 10

        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 40000000000,
            'available' => 60000000000,
            'usage_percent' => 40.0,
            'collected_at' => $day1,
        ]);

        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 45000000000,
            'available' => 55000000000,
            'usage_percent' => 45.0,
            'collected_at' => $day2,
        ]);

        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 50000000000,
            'available' => 50000000000,
            'usage_percent' => 50.0,
            'collected_at' => $day3,
        ]);

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(3, $result);
        $this->assertEquals('2026-09-10', $result[0]->date);
        $this->assertEquals('2026-09-08', $result[1]->date);
        $this->assertEquals('2026-09-05', $result[2]->date);

        // Growth Sep 10 vs Sep 08 = 50GB - 45GB = 5GB
        $this->assertEquals(5000000000, $result[0]->growthBytes);
    }

    public function test_scenario_7_no_historical_disk_data_returns_empty_array()
    {
        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scenario_8_disk_capacity_changes_growth_calculated_on_used_storage()
    {
        $day1 = Carbon::parse('2026-09-09 10:00:00');
        $day2 = Carbon::parse('2026-09-10 10:00:00');

        // Day 1: Total capacity 100 GB, used 50 GB
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 100000000000,
            'used' => 50000000000,
            'available' => 50000000000,
            'usage_percent' => 50.0,
            'collected_at' => $day1,
        ]);

        // Day 2: Expanded total capacity to 200 GB, used 60 GB
        DiskMetric::create([
            'server_id' => $this->server->id,
            'hostname' => 'mutif',
            'total' => 200000000000,
            'used' => 60000000000,
            'available' => 140000000000,
            'usage_percent' => 30.0, // Percentage dropped from 50% to 30%, but used storage increased by 10 GB!
            'collected_at' => $day2,
        ]);

        $result = $this->service->getDailyGrowth($this->server, 5);

        $this->assertCount(2, $result);
        $this->assertEquals('2026-09-10', $result[0]->date);
        // Growth must be positive 10 GB based on used bytes, ignoring usage_percent drop
        $this->assertEquals(10000000000, $result[0]->growthBytes);
        $this->assertStringStartsWith('+', $result[0]->growthFormatted);
    }
}
