<?php

namespace Tests\Unit;

use App\Domain\Monitoring\Data\BatchCommandResult;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Memory\MemoryDetailService;
use App\Services\Monitoring\RemoteCommandService;
use Mockery;
use Illuminate\Support\Facades\Log;
use Exception;
use Tests\TestCase;

class MemoryDetailServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_memory_detail_returns_success_with_parsed_data()
    {
        $server = Mockery::mock(MonitoredServer::class)->makePartial();
        $server->id = 1;

        $remoteCommands = Mockery::mock(RemoteCommandService::class);
        
        $freeOutput = "              total        used        free      shared  buff/cache   available
Mem:      167772160    80000000    10000000     2000000    77772160    83886080
Swap:      10000000     5000000     5000000";

        $meminfoOutput = "MemTotal:       163840 kB
MemFree:         10000 kB
MemAvailable:    81920 kB
Buffers:         20000 kB
Cached:          55000 kB
SwapTotal:       10000 kB
SwapFree:         5000 kB";

        $psOutput = "  PID COMMAND         USER       RSS %MEM
    1 systemd         root       10000  0.5
   10 apache2         www-data   50000  2.5
    5 kworker         root           0  0.0
";

        $batchResult = new BatchCommandResult([
            'free' => $freeOutput,
            'meminfo' => $meminfoOutput,
            'ps' => $psOutput,
        ]);

        $remoteCommands->shouldReceive('executeMany')
            ->once()
            ->andReturn($batchResult);

        $service = new MemoryDetailService($remoteCommands);
        $result = $service->getMemoryDetail($server);

        $this->assertTrue($result['success']);
        
        // Breakdown checks
        $breakdown = $result['breakdown'];
        $this->assertEquals(167772160, $breakdown['total']);
        $this->assertEquals(80000000, $breakdown['used']);
        $this->assertEquals(10000000, $breakdown['free']);
        $this->assertEquals(2000000, $breakdown['shared']);
        $this->assertEquals(83886080, $breakdown['available']);
        $this->assertEquals(10000000, $breakdown['swap_total']);
        $this->assertEquals(5000000, $breakdown['swap_used']);
        
        // Buffers and Cache parsed from meminfo
        $this->assertEquals(20000 * 1024, $breakdown['buffers']);
        $this->assertEquals(55000 * 1024, $breakdown['cache']);

        // Process checks (should be sorted by RSS descending)
        $processes = $result['processes'];
        $this->assertCount(3, $processes);
        
        $this->assertEquals('apache2', $processes[0]['name']);
        $this->assertEquals(50000 * 1024, $processes[0]['rss_bytes']);
        $this->assertEquals('www-data', $processes[0]['user']);

        $this->assertEquals('systemd', $processes[1]['name']);
        $this->assertEquals(10000 * 1024, $processes[1]['rss_bytes']);

        $this->assertEquals('kworker', $processes[2]['name']);
        $this->assertEquals(0, $processes[2]['rss_bytes']);

        // Analysis check (Swap usage is 50%, not over 50%, so no pressure from swap, available is > 10%)
        $analysis = $result['analysis'];
        $this->assertFalse($analysis['has_pressure']);
        $this->assertEquals('Memory levels appear normal. No immediate pressure detected.', $analysis['summary']);
        $this->assertStringContainsString('apache2 is the highest consumer', $analysis['top_process_note']);
    }

    public function test_memory_pressure_detected_when_available_low()
    {
        $server = Mockery::mock(MonitoredServer::class)->makePartial();
        $server->id = 1;

        $remoteCommands = Mockery::mock(RemoteCommandService::class);
        
        // Available is very low
        $freeOutput = "              total        used        free      shared  buff/cache   available
Mem:      100000000    95000000     2000000           0     3000000     4000000";

        $batchResult = new BatchCommandResult([
            'free' => $freeOutput,
            'meminfo' => null,
            'ps' => null,
        ]);

        $remoteCommands->shouldReceive('executeMany')
            ->once()
            ->andReturn($batchResult);

        $service = new MemoryDetailService($remoteCommands);
        $result = $service->getMemoryDetail($server);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['analysis']['has_pressure']);
        $this->assertStringContainsString('Available RAM is extremely low', $result['analysis']['pressure_reason']);
    }

    public function test_memory_pressure_detected_when_swap_high()
    {
        $server = Mockery::mock(MonitoredServer::class)->makePartial();
        $server->id = 1;

        $remoteCommands = Mockery::mock(RemoteCommandService::class);
        
        // Swap usage is 60%
        $freeOutput = "              total        used        free      shared  buff/cache   available
Mem:      100000000    50000000    10000000           0    40000000    40000000
Swap:      10000000     6000000     4000000";

        $batchResult = new BatchCommandResult([
            'free' => $freeOutput,
            'meminfo' => null,
            'ps' => null,
        ]);

        $remoteCommands->shouldReceive('executeMany')
            ->once()
            ->andReturn($batchResult);

        $service = new MemoryDetailService($remoteCommands);
        $result = $service->getMemoryDetail($server);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['analysis']['has_pressure']);
        $this->assertStringContainsString('relying on swap memory', $result['analysis']['pressure_reason']);
    }

    public function test_empty_or_failed_ssh_handled_gracefully()
    {
        Log::shouldReceive('error')->once();

        $server = Mockery::mock(MonitoredServer::class)->makePartial();
        $server->id = 1;

        $remoteCommands = Mockery::mock(RemoteCommandService::class);
        $remoteCommands->shouldReceive('executeMany')
            ->once()
            ->andThrow(new Exception('SSH timeout'));

        $service = new MemoryDetailService($remoteCommands);
        $result = $service->getMemoryDetail($server);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to connect to server: SSH timeout', $result['message']);
    }

    public function test_parsing_fallback_if_free_fails_but_meminfo_succeeds()
    {
        $server = Mockery::mock(MonitoredServer::class)->makePartial();
        $server->id = 1;

        $remoteCommands = Mockery::mock(RemoteCommandService::class);
        
        // Only meminfo available
        $meminfoOutput = "MemTotal:       100000 kB
MemFree:         20000 kB
MemAvailable:    30000 kB
Buffers:         10000 kB
Cached:          40000 kB
SwapTotal:       20000 kB
SwapFree:        15000 kB";

        $batchResult = new BatchCommandResult([
            'free' => null,
            'meminfo' => $meminfoOutput,
            'ps' => null,
        ]);

        $remoteCommands->shouldReceive('executeMany')
            ->once()
            ->andReturn($batchResult);

        $service = new MemoryDetailService($remoteCommands);
        $result = $service->getMemoryDetail($server);

        $this->assertTrue($result['success']);
        $breakdown = $result['breakdown'];
        
        $this->assertEquals(100000 * 1024, $breakdown['total']);
        $this->assertEquals(20000 * 1024, $breakdown['free']);
        // used = total - free - buffers - cache = 100 - 20 - 10 - 40 = 30
        $this->assertEquals(30000 * 1024, $breakdown['used']);
        $this->assertEquals(5000 * 1024, $breakdown['swap_used']);
    }
    public function test_disconnect_is_called_even_on_exception()
    {
        $server = Mockery::mock(MonitoredServer::class)->makePartial();
        $server->id = 1;

        $remoteCommands = Mockery::mock(RemoteCommandService::class);
        $remoteCommands->shouldReceive('executeMany')
            ->once()
            ->andThrow(new Exception('Some error'));
            
        $remoteCommands->shouldReceive('disconnect')
            ->once()
            ->with($server);

        $service = new MemoryDetailService($remoteCommands);
        $service->getMemoryDetail($server);
        
        // Assertion handled by Mockery
        $this->assertTrue(true);
    }
}
