<?php

namespace Tests\Feature;

use App\Models\MonitoredServer;
use App\Services\Monitoring\DiskStorageGrowthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\AuthorizedEmail;
use Carbon\Carbon;

class DiskStorageGrowthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Authenticate so any routes can be accessed if needed, 
        // though we're mostly testing the service here.
        $user = AuthorizedEmail::factory()->create();
        $this->actingAs($user);
    }

    public function test_get_daily_growth_does_not_return_duplicate_dates()
    {
        $server = MonitoredServer::create([
            'name' => 'Test Server',
            'hostname' => 'test-server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_username' => 'root',
            'ssh_password' => 'pass'
        ]);

        $service = new DiskStorageGrowthService();

        // Create identical snapshots on the exact same date and time
        DB::table('disk_metrics')->insert([
            ['server_id' => $server->id, 'hostname' => 'test-server', 'total' => 10000, 'available' => 9000, 'usage_percent' => 10, 'used' => 1000, 'collected_at' => Carbon::parse('2026-09-25 10:00:00')],
            ['server_id' => $server->id, 'hostname' => 'test-server', 'total' => 10000, 'available' => 8000, 'usage_percent' => 20, 'used' => 2000, 'collected_at' => Carbon::parse('2026-09-25 10:00:00')],
            ['server_id' => $server->id, 'hostname' => 'test-server', 'total' => 10000, 'available' => 7000, 'usage_percent' => 30, 'used' => 3000, 'collected_at' => Carbon::parse('2026-09-24 15:00:00')],
        ]);

        $growth = $service->getDailyGrowth($server, 5);

        // There are 2 distinct dates (25th and 24th)
        $this->assertCount(2, $growth);
        
        $this->assertEquals('2026-09-25', $growth[0]->date);
        $this->assertEquals('2026-09-24', $growth[1]->date);
        
        // Assert no duplicate dates
        $dates = array_map(fn($g) => $g->date, $growth);
        $this->assertEquals(count($dates), count(array_unique($dates)));
    }
}
