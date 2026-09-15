<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Models\PostgresqlDatabaseSizeSnapshot;
use App\Services\Monitoring\Collectors\PostgreSqlDatabaseSizeCollector;
use App\Services\Monitoring\PostgreSqlDatabaseSizeSnapshotProvider;
use App\Services\Monitoring\RemoteCommandService;
use App\Services\Monitoring\Support\PostgreSqlCommandBuilder;
use App\Domain\Monitoring\Data\RemoteCommandResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class PostgreSqlDatabaseSizeCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_runs_for_multi_server_with_different_postgres_ports()
    {
        $server1 = MonitoredServer::create([
            'name' => 'legacy-pg92-server',
            'hostname' => '10.0.0.1',
            'web_server' => 'nginx',
            'postgres_port' => 5433,
            'is_active' => true,
        ]);

        $server2 = MonitoredServer::create([
            'name' => 'modern-pg16-server',
            'hostname' => '10.0.0.2',
            'web_server' => 'nginx',
            'postgres_port' => 5432,
            'is_active' => true,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $expectedCmd1 = $builder->build($server1, "SELECT oid, datname, pg_database_size(datname) AS size_bytes\nFROM pg_database\nWHERE datallowconn = true;");
        $expectedCmd2 = $builder->build($server2, "SELECT oid, datname, pg_database_size(datname) AS size_bytes\nFROM pg_database\nWHERE datallowconn = true;");

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function ($server, $cmd) use ($server1, $server2, $expectedCmd1, $expectedCmd2) {
                if ($server->id === $server1->id) {
                    $this->assertEquals($expectedCmd1, $cmd);
                    return new RemoteCommandResult(true, "193071|exapro_mutif|500000000\n13408|postgres|8000000", null);
                }
                if ($server->id === $server2->id) {
                    $this->assertEquals($expectedCmd2, $cmd);
                    return new RemoteCommandResult(true, "16384|sip_mutif_db|1200000000\n13408|postgres|10000000", null);
                }
                return new RemoteCommandResult(false, null, 'Unknown server');
            });

        $collector = new PostgreSqlDatabaseSizeCollector($mockCommands, $builder);

        $collector->collect($server1);
        $collector->collect($server2);

        $this->assertDatabaseHas('postgresql_database_size_snapshots', [
            'server_id' => $server1->id,
            'database_oid' => '193071',
            'database_name' => 'exapro_mutif',
            'size_bytes' => 500000000,
        ]);

        $this->assertDatabaseHas('postgresql_database_size_snapshots', [
            'server_id' => $server2->id,
            'database_oid' => '16384',
            'database_name' => 'sip_mutif_db',
            'size_bytes' => 1200000000,
        ]);
    }

    public function test_collector_failsafe_when_server_has_no_postgres_port_or_ssh_fails()
    {
        $serverNoPg = MonitoredServer::create([
            'name' => 'no-pg-server',
            'hostname' => '10.0.0.3',
            'web_server' => 'nginx',
            'postgres_port' => 0,
            'is_active' => true,
        ]);

        $serverErr = MonitoredServer::create([
            'name' => 'error-pg-server',
            'hostname' => '10.0.0.4',
            'web_server' => 'nginx',
            'postgres_port' => 5432,
            'is_active' => true,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('SSH timeout'));

        $collector = new PostgreSqlDatabaseSizeCollector($mockCommands, $builder);
        $provider = new PostgreSqlDatabaseSizeSnapshotProvider($collector);

        // Must not throw exception
        $provider->getSnapshots($serverNoPg);
        $provider->getSnapshots($serverErr);

        $this->assertEquals(0, PostgresqlDatabaseSizeSnapshot::count());
    }

    public function test_monitoring_cleanup_prunes_snapshots_older_than_30_days()
    {
        $server = MonitoredServer::create([
            'name' => 'cleanup-test-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'nginx',
            'postgres_port' => 5432,
            'is_active' => true,
        ]);

        // 35 days old snapshot (should be pruned)
        PostgresqlDatabaseSizeSnapshot::create([
            'server_id' => $server->id,
            'database_oid' => '10001',
            'database_name' => 'old_db',
            'size_bytes' => 1000000,
            'snapshot_at' => now()->subDays(35),
        ]);

        // 10 days old snapshot (should be kept)
        PostgresqlDatabaseSizeSnapshot::create([
            'server_id' => $server->id,
            'database_oid' => '10001',
            'database_name' => 'old_db',
            'size_bytes' => 1000000,
            'snapshot_at' => now()->subDays(10),
        ]);

        Artisan::call('monitoring:cleanup');

        $this->assertEquals(1, PostgresqlDatabaseSizeSnapshot::count());
        $this->assertDatabaseHas('postgresql_database_size_snapshots', [
            'server_id' => $server->id,
            'database_oid' => '10001',
        ]);
    }
}
