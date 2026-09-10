<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Collectors\DiskFilesystemCollector;
use App\Services\Monitoring\DiskFilesystemSnapshotProvider;
use App\Services\Monitoring\MetricSnapshotProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DiskFilesystemSnapshotProviderTest extends TestCase
{
    use RefreshDatabase;

    private MonitoredServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = MonitoredServer::create([
            'name' => 'provider-test-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'nginx',
            'is_active' => true,
        ]);
    }

    public function test_provider_implements_metric_snapshot_provider_interface()
    {
        $collector = $this->createMock(DiskFilesystemCollector::class);
        $provider = new DiskFilesystemSnapshotProvider($collector);

        $this->assertInstanceOf(MetricSnapshotProvider::class, $provider);
    }

    public function test_get_snapshots_delegates_to_collector_and_returns_array()
    {
        $collector = $this->createMock(DiskFilesystemCollector::class);
        $collector->expects($this->once())
            ->method('collect')
            ->with($this->server);

        $provider = new DiskFilesystemSnapshotProvider($collector);
        $result = $provider->getSnapshots($this->server);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
