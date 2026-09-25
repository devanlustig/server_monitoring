<?php

namespace Tests\Feature;

use App\Models\MonitoredServer;
use App\Models\DiskDirectorySnapshot;
use App\Models\DiskMetric;
use App\Services\Monitoring\DiskGrowthDetailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiskGrowthDetailSplitTest extends TestCase
{
    use RefreshDatabase;

    private MonitoredServer $server;
    private DiskGrowthDetailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = MonitoredServer::create([
            'name' => 'split-test-server',
            'hostname' => '127.0.0.1',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_username' => 'root',
            'ssh_password' => 'pass'
        ]);
        
        $this->service = app(DiskGrowthDetailService::class);
    }

    public function test_positive_net_growth_shows_growth_section_and_reduction_section_if_applicable()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        // Total Net Growth = +5 GB
        DiskMetric::create(['server_id' => $this->server->id, 'hostname' => '127.0.0.1', 'total' => 20000000000, 'used' => 10000000000, 'available' => 10000000000, 'usage_percent' => 50, 'collected_at' => $prevDate]);
        DiskMetric::create(['server_id' => $this->server->id, 'hostname' => '127.0.0.1', 'total' => 20000000000, 'used' => 15000000000, 'available' => 5000000000, 'usage_percent' => 75, 'collected_at' => $targetDate]);

        // Grew by +7 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log', 'size_bytes' => 1000000000, 'snapshot_at' => $prevDate]);
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log', 'size_bytes' => 8000000000, 'snapshot_at' => $targetDate]);

        // Reduced by -2 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/home', 'size_bytes' => 5000000000, 'snapshot_at' => $prevDate]);
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/home', 'size_bytes' => 3000000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $this->assertEquals(5000000000, $result['totalGrowthBytes']);
        
        $this->assertCount(1, $result['growthDirectories']);
        $this->assertEquals('/var/log', $result['growthDirectories'][0]['path']);
        $this->assertEquals(7000000000, $result['growthDirectories'][0]['growthBytes']);

        $this->assertCount(1, $result['reductionDirectories']);
        $this->assertEquals('/home', $result['reductionDirectories'][0]['path']);
        $this->assertEquals(-2000000000, $result['reductionDirectories'][0]['growthBytes']);
    }

    public function test_negative_net_growth_shows_reduction_section_and_growth_section_if_applicable()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        // Total Net Growth = -3 GB
        DiskMetric::create(['server_id' => $this->server->id, 'hostname' => '127.0.0.1', 'total' => 20000000000, 'used' => 15000000000, 'available' => 5000000000, 'usage_percent' => 75, 'collected_at' => $prevDate]);
        DiskMetric::create(['server_id' => $this->server->id, 'hostname' => '127.0.0.1', 'total' => 20000000000, 'used' => 12000000000, 'available' => 8000000000, 'usage_percent' => 60, 'collected_at' => $targetDate]);

        // Grew by +1 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log', 'size_bytes' => 1000000000, 'snapshot_at' => $prevDate]);
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log', 'size_bytes' => 2000000000, 'snapshot_at' => $targetDate]);

        // Reduced by -4 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/home', 'size_bytes' => 5000000000, 'snapshot_at' => $prevDate]);
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/home', 'size_bytes' => 1000000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $this->assertEquals(-3000000000, $result['totalGrowthBytes']);
        
        $this->assertCount(1, $result['growthDirectories']);
        $this->assertEquals('/var/log', $result['growthDirectories'][0]['path']);
        
        $this->assertCount(1, $result['reductionDirectories']);
        $this->assertEquals('/home', $result['reductionDirectories'][0]['path']);
    }

    public function test_mathematical_reconciliation_of_untracked_net_growth()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        // Net Growth = -34.4 GB (-36936718745 bytes)
        $netGrowth = -36936718745;
        DiskMetric::create(['server_id' => $this->server->id, 'hostname' => '127.0.0.1', 'total' => 100000000000, 'used' => 50000000000, 'available' => 50000000000, 'usage_percent' => 50, 'collected_at' => $prevDate]);
        DiskMetric::create(['server_id' => $this->server->id, 'hostname' => '127.0.0.1', 'total' => 100000000000, 'used' => 50000000000 + $netGrowth, 'available' => 50000000000 - $netGrowth, 'usage_percent' => 45, 'collected_at' => $targetDate]);

        // Tracked Positive = +1.5 GB (+1610612736 bytes)
        // Let's create one Top Growth directory (+1.0 GB) and 5 small ones (+0.1 GB each) to make +1.5 GB tracked positive
        $topGrowth = 1073741824; // 1 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log/top_growth', 'size_bytes' => 1000000000, 'snapshot_at' => $prevDate]);
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log/top_growth', 'size_bytes' => 1000000000 + $topGrowth, 'snapshot_at' => $targetDate]);

        for ($i = 1; $i <= 5; $i++) {
            $smallGrowth = 107374182; // ~0.1 GB
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/var/log/small_$i", 'size_bytes' => 100000000, 'snapshot_at' => $prevDate]);
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/var/log/small_$i", 'size_bytes' => 100000000 + $smallGrowth, 'snapshot_at' => $targetDate]);
        }
        // Wait, total top 10 will consume all 6! 
        // I need to create 10 directories that take up 1.0 GB, and then some more to be "Other".
        // Let's create 10 directories that grew by 100 MB each (Top Growth = 1.0 GB)
        for ($i = 1; $i <= 10; $i++) {
            $g = 107374182; // 0.1 GB
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/var/top_$i", 'size_bytes' => 100000000, 'snapshot_at' => $prevDate]);
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/var/top_$i", 'size_bytes' => 100000000 + $g, 'snapshot_at' => $targetDate]);
        }
        // Create 5 more directories that grew by 100 MB each (Total Positive Tracked = 1.5 GB)
        for ($i = 11; $i <= 15; $i++) {
            $g = 107374182; // 0.1 GB
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/var/top_$i", 'size_bytes' => 100000000, 'snapshot_at' => $prevDate]);
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/var/top_$i", 'size_bytes' => 100000000 + $g, 'snapshot_at' => $targetDate]);
        }
        $expectedTopGrowth = 10 * 107374182;
        
        // Tracked Negative = -22.0 GB (-23622320128 bytes)
        // 10 directories that shrank by 2.0 GB each (Top Reduction = -20.0 GB)
        for ($i = 1; $i <= 10; $i++) {
            $r = -2147483648; // -2.0 GB
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/home/top_$i", 'size_bytes' => 3000000000, 'snapshot_at' => $prevDate]);
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/home/top_$i", 'size_bytes' => 3000000000 + $r, 'snapshot_at' => $targetDate]);
        }
        $expectedTopReduction = 10 * -2147483648;
        // 2 more directories that shrank by 1.0 GB each (Total Negative Tracked = -22.0 GB)
        for ($i = 11; $i <= 12; $i++) {
            $r = -1073741824; // -1.0 GB
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/home/top_$i", 'size_bytes' => 3000000000, 'snapshot_at' => $prevDate]);
            DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => "/home/top_$i", 'size_bytes' => 3000000000 + $r, 'snapshot_at' => $targetDate]);
        }

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $this->assertEquals($netGrowth, $result['totalGrowthBytes']);

        // Sum Top Growth + Other Growth + Top Reduction + Other Reduction
        $topGrowthSum = 0;
        $otherGrowth = 0;
        foreach ($result['growthDirectories'] as $dir) {
            if ($dir['path'] === 'Other (unclassified growth)') {
                $otherGrowth = $dir['growthBytes'];
            } else {
                $topGrowthSum += $dir['growthBytes'];
            }
        }

        $topReductionSum = 0;
        $otherReduction = 0;
        foreach ($result['reductionDirectories'] as $dir) {
            if ($dir['path'] === 'Other (unclassified reduction)') {
                $otherReduction = $dir['growthBytes'];
            } else {
                $topReductionSum += $dir['growthBytes'];
            }
        }

        // Mathematical Reconciliation (The Ultimate Proof)
        $this->assertEquals(
            $netGrowth,
            $topGrowthSum + $otherGrowth + $topReductionSum + $otherReduction,
            'Top Growth + Other Growth + Top Reduction + Other Reduction == Net Growth'
        );

        $this->assertGreaterThan(0, $otherGrowth);
        $this->assertLessThan(0, $otherReduction);
    }
}
