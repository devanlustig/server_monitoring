<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Models\DiskDirectorySnapshot;
use App\Models\DiskFileSnapshot;
use App\Models\PostgresqlDatabaseSizeSnapshot;
use App\Services\Monitoring\DiskGrowthDetailService;
use App\Services\Monitoring\DiskStorageGrowthService;
use App\Services\Monitoring\RemoteCommandService;
use App\Services\Monitoring\Support\PostgreSqlCommandBuilder;
use App\Domain\Monitoring\Data\RemoteCommandResult;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DiskGrowthDetailServiceTest extends TestCase
{
    use RefreshDatabase;

    private MonitoredServer $server;
    private DiskGrowthDetailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = MonitoredServer::create([
            'name' => 'growth-detail-test-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'nginx',
            'postgres_port' => 5432,
            'is_active' => true,
        ]);
        
        $storageGrowthService = new DiskStorageGrowthService();
        $commands = $this->createMock(RemoteCommandService::class);
        $commands->method('execute')->willReturn(new RemoteCommandResult(false, '', null));
        $builder = new PostgreSqlCommandBuilder();
        $this->service = new DiskGrowthDetailService($storageGrowthService, $commands, $builder);
    }

    public function test_directory_growth_delta_calculation_with_valid_previous_snapshot()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        // Previous Snapshots (09 Sep)
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/lib/postgresql', 'size_bytes' => 1000000000, 'snapshot_at' => $prevDate]);
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log/apache2', 'size_bytes' => 500000000, 'snapshot_at' => $prevDate]);

        // Target Snapshots (10 Sep)
        // /var/lib/postgresql grew by 1.5 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/lib/postgresql', 'size_bytes' => 2500000000, 'snapshot_at' => $targetDate]);
        // /var/log/apache2 grew by 0.1 GB
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/log/apache2', 'size_bytes' => 600000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $this->assertNotEmpty($result['growthDirectories']);
        $this->assertEquals('/var/lib/postgresql', $result['growthDirectories'][0]['path']);
        $this->assertEquals(1000000000, $result['growthDirectories'][0]['previousSizeBytes']);
        $this->assertEquals(1500000000, $result['growthDirectories'][0]['growthBytes']);
    }

    public function test_directory_without_previous_snapshot_returns_null_previous_size_and_null_growth()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        // Target Snapshot exists, but NO previous snapshot exists on prior dates
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/lib/postgresql', 'size_bytes' => 87600000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $this->assertNotEmpty($result['growthDirectories']);
        $this->assertEquals('/var/lib/postgresql', $result['growthDirectories'][0]['path']);
        // Must NOT default to 0 B or compute +87.6 GB fake growth!
        $this->assertNull($result['growthDirectories'][0]['previousSizeBytes']);
        $this->assertNull($result['growthDirectories'][0]['growthBytes']);
        $this->assertEquals('N/A', $result['growthDirectories'][0]['previousSizeFormatted']);
        $this->assertEquals('N/A', $result['growthDirectories'][0]['growthFormatted']);
    }

    public function test_file_growth_delta_calculation_and_top_15_limit()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        // Create 20 file snapshots
        for ($i = 1; $i <= 20; $i++) {
            $filePath = "/var/lib/postgresql/file_{$i}.db";
            DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => $filePath, 'size_bytes' => $i * 1000000, 'snapshot_at' => $prevDate]);
            DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => $filePath, 'size_bytes' => ($i * 1000000) + ($i * 500000), 'snapshot_at' => $targetDate]);
        }

        $result = $this->service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        // Must be capped at top 15 files
        $this->assertCount(15, $result['files']);
        // Sorted by growthBytes DESC: file 20 had highest growth (20 * 500000 = 10,000,000 bytes)
        $this->assertEquals('file_20.db', $result['files'][0]['filename']);
        $this->assertEquals(10000000, $result['files'][0]['growthBytes']);
    }

    public function test_file_without_previous_snapshot_returns_null_previous_size_and_null_growth()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => '/var/lib/postgresql/new_wal.log', 'size_bytes' => 500000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertNull($result['files'][0]['previousSizeBytes']);
        $this->assertNull($result['files'][0]['growthBytes']);
        $this->assertEquals('N/A', $result['files'][0]['previousSizeFormatted']);
        $this->assertEquals('N/A', $result['files'][0]['growthFormatted']);
    }

    public function test_file_growth_when_directory_growth_large_and_file_at_depth_greater_than_3()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        $deepFilePath = '/var/lib/postgresql/16/main/base/16384/24581';
        $deepDirDir = dirname($deepFilePath); // /var/lib/postgresql/16/main/base/16384

        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $deepDirDir, 'file_path' => $deepFilePath, 'size_bytes' => 100000000, 'snapshot_at' => $prevDate]);
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $deepDirDir, 'file_path' => $deepFilePath, 'size_bytes' => 522100000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('24581', $result['files'][0]['filename']);
        $this->assertEquals($deepFilePath, $result['files'][0]['path']);
        $this->assertEquals(100000000, $result['files'][0]['previousSizeBytes']);
        $this->assertEquals(422100000, $result['files'][0]['growthBytes']);
    }

    public function test_new_file_with_existing_previous_snapshot_returns_zero_previous_size_and_current_size_growth()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        // Existing file in previous snapshot at depth 8
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => '/var/lib/postgresql/16/main/pg_wal', 'file_path' => '/var/lib/postgresql/16/main/pg_wal/000000010000000000000000', 'size_bytes' => 16000000, 'snapshot_at' => $prevDate]);
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => '/var/lib/postgresql/16/main/pg_wal', 'file_path' => '/var/lib/postgresql/16/main/pg_wal/000000010000000000000000', 'size_bytes' => 16000000, 'snapshot_at' => $targetDate]);

        // Brand new file created on target date at same depth
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => '/var/lib/postgresql/16/main/pg_wal', 'file_path' => '/var/lib/postgresql/16/main/pg_wal/000000010000000000000001', 'size_bytes' => 400000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        // Top file should be the brand new file with highest growth (400 MB)
        $newFile = $result['files'][0];
        $this->assertEquals('000000010000000000000001', $newFile['filename']);
        $this->assertEquals(0, $newFile['previousSizeBytes']);
        $this->assertEquals('0 B', $newFile['previousSizeFormatted']);
        $this->assertEquals(400000000, $newFile['growthBytes']);
    }

    public function test_file_missing_in_previous_snapshot_due_to_non_comparable_coverage_returns_null_previous_and_growth()
    {
        $prevDate = Carbon::parse('2026-09-13 12:00:00');
        $targetDate = Carbon::parse('2026-09-14 12:00:00');
        $directory = '/var/lib/postgresql';

        // Previous snapshot (13 Sep) only collected shallow config files (depth <= 6, e.g. /var/lib/postgresql/16/main/postgresql.conf)
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => '/var/lib/postgresql/16/main', 'file_path' => '/var/lib/postgresql/16/main/postgresql.conf', 'size_bytes' => 25000, 'snapshot_at' => $prevDate]);

        // Target snapshot (14 Sep) collected deep relation files at depth 8 (1.0 GB)
        $deepFilePath = '/var/lib/postgresql/16/main/base/16384/24581';
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => '/var/lib/postgresql/16/main/base/16384', 'file_path' => $deepFilePath, 'size_bytes' => 1073741824, 'snapshot_at' => $targetDate]);

        $result = $this->service->getFileGrowth($this->server, '2026-09-14', $directory);

        $this->assertNotEmpty($result['files']);
        
        $deepFileResult = null;
        foreach ($result['files'] as $f) {
            if ($f['path'] === $deepFilePath) {
                $deepFileResult = $f;
                break;
            }
        }

        $this->assertNotNull($deepFileResult);
        $this->assertEquals('24581', $deepFileResult['filename']);
        // Must return NULL for previousSizeBytes and growthBytes, NOT 0 and +1.0 GB!
        $this->assertNull($deepFileResult['previousSizeBytes']);
        $this->assertNull($deepFileResult['growthBytes']);
        $this->assertEquals('N/A', $deepFileResult['previousSizeFormatted']);
        $this->assertEquals('N/A', $deepFileResult['growthFormatted']);
    }

    public function test_existing_file_growth()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => '/var/lib/postgresql/app.db', 'size_bytes' => 100000000, 'snapshot_at' => $prevDate]);
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => '/var/lib/postgresql/app.db', 'size_bytes' => 250000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('app.db', $result['files'][0]['filename']);
        $this->assertEquals(100000000, $result['files'][0]['previousSizeBytes']);
        $this->assertEquals(150000000, $result['files'][0]['growthBytes']);
    }

    public function test_directory_without_growth()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => '/var/lib/postgresql/static.db', 'size_bytes' => 50000000, 'snapshot_at' => $prevDate]);
        DiskFileSnapshot::create(['server_id' => $this->server->id, 'directory_path' => $directory, 'file_path' => '/var/lib/postgresql/static.db', 'size_bytes' => 50000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('static.db', $result['files'][0]['filename']);
        $this->assertEquals(50000000, $result['files'][0]['previousSizeBytes']);
        $this->assertEquals(0, $result['files'][0]['growthBytes']);
        $this->assertEquals('0 GB', $result['files'][0]['growthFormatted']);
    }

    public function test_non_overlapping_paths_filter_prevents_double_counting()
    {
        $paths = [
            '/',
            '/var',
            '/var/lib',
            '/var/lib/postgresql',
            '/var/log',
            '/home',
            '/proc',
            '/sys',
        ];

        $filtered = $this->service->filterNonOverlappingPaths($paths);

        $this->assertNotContains('/', $filtered);
        $this->assertNotContains('/var', $filtered);
        $this->assertNotContains('/var/lib', $filtered);
        $this->assertNotContains('/proc', $filtered);

        $this->assertContains('/var/lib/postgresql', $filtered);
        $this->assertContains('/var/log', $filtered);
        $this->assertContains('/home', $filtered);
    }

    public function test_directory_path_security_sanitization_rejects_malicious_paths()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->sanitizeDirectoryPath('/var/www/../../etc/passwd');
    }

    public function test_directory_path_security_sanitization_rejects_pseudo_filesystems()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->sanitizeDirectoryPath('/proc/sys/fs');
    }

    public function test_large_production_dataset_memory_efficiency()
    {
        $prevDate = Carbon::parse('2026-09-13 12:00:00');
        $targetDate = Carbon::parse('2026-09-14 12:00:00');
        $directory = '/var/lib/postgresql';

        // Insert 10,000 file snapshot rows in bulk chunks
        $rows = [];
        for ($i = 1; $i <= 5000; $i++) {
            $rows[] = [
                'server_id' => $this->server->id,
                'directory_path' => "/var/lib/postgresql/16/main/base/16384",
                'file_path' => "/var/lib/postgresql/16/main/base/16384/rel_{$i}",
                'size_bytes' => $i * 1000,
                'snapshot_at' => $prevDate,
            ];
            $rows[] = [
                'server_id' => $this->server->id,
                'directory_path' => "/var/lib/postgresql/16/main/base/16384",
                'file_path' => "/var/lib/postgresql/16/main/base/16384/rel_{$i}",
                'size_bytes' => ($i * 1000) + ($i * 500),
                'snapshot_at' => $targetDate,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('disk_file_snapshots')->insert($chunk);
        }

        $startMemory = memory_get_usage();

        $result = $this->service->getFileGrowth($this->server, '2026-09-14', $directory);

        $endMemory = memory_get_usage();
        $memoryDelta = $endMemory - $startMemory;

        $this->assertNotEmpty($result['files']);
        // Returns strictly top 15 files
        $this->assertCount(15, $result['files']);
        // Memory delta during query execution must be less than 5 MB
        $this->assertLessThan(5 * 1024 * 1024, $memoryDelta);
        // Top file is rel_5000 with highest growth (5000 * 500 = 2,500,000 bytes)
        $this->assertEquals('rel_5000', $result['files'][0]['filename']);
        $this->assertEquals(2500000, $result['files'][0]['growthBytes']);
    }

    public function test_large_dataset_directory_growth_memory_efficiency()
    {
        $baseDate = Carbon::parse('2026-09-10 12:00:00');
        $paths = ['/var', '/var/lib', '/var/lib/postgresql', '/var/log', '/var/www', '/home', '/tmp', '/opt', '/etc', '/srv'];

        // Insert directory snapshots every 2 minutes across 3 days (720 runs/day * 10 paths = 7,200 rows/day * 3 = 21,600 rows!)
        $rows = [];
        for ($day = 0; $day < 3; $day++) {
            $dayDate = $baseDate->copy()->subDays($day);
            for ($run = 0; $run < 720; $run++) {
                $snapshotAt = $dayDate->copy()->subMinutes($run * 2);
                foreach ($paths as $idx => $path) {
                    $rows[] = [
                        'server_id' => $this->server->id,
                        'path' => $path,
                        'size_bytes' => 1000000000 + ((2 - $day) * 100000000) + ($idx * 5000000),
                        'snapshot_at' => $snapshotAt->toDateTimeString(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('disk_directory_snapshots')->insert($chunk);
        }

        $startMemory = memory_get_usage();

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $endMemory = memory_get_usage();
        $memoryDelta = $endMemory - $startMemory;

        $this->assertNotEmpty($result['growthDirectories']);
        // Memory delta must be minimal (< 3 MB) because only single timestamp rows are fetched
        $this->assertLessThan(3 * 1024 * 1024, $memoryDelta);
        // Correct leaf paths selected
        $this->assertEquals('/srv', $result['growthDirectories'][0]['path']);
    }

    public function test_postgresql_oid_extracted_and_mapped_for_server_with_custom_port_and_cluster()
    {
        $server1 = MonitoredServer::create([
            'name' => 'legacy-pg92-server',
            'hostname' => '10.0.0.1',
            'web_server' => 'nginx',
            'postgres_port' => 5433,
            'is_active' => true,
        ]);

        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';
        $filePath = '/var/lib/postgresql/9.2/main92/base/193071/520109';

        DiskFileSnapshot::create([
            'server_id' => $server1->id,
            'directory_path' => dirname($filePath),
            'file_path' => $filePath,
            'size_bytes' => 50000000,
            'snapshot_at' => $targetDate,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $expectedCmd = $builder->build($server1, 'SELECT oid, datname FROM pg_database;');

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->once())
            ->method('execute')
            ->with($server1, $expectedCmd)
            ->willReturn(new RemoteCommandResult(true, "193071|exapro_mutif_02052021_2\n13408|postgres", null));

        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);
        $result = $service->getFileGrowth($server1, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('exapro_mutif_02052021_2', $result['files'][0]['database_name']);
    }

    public function test_postgresql_oid_extracted_and_mapped_for_server_with_standard_port()
    {
        $server2 = MonitoredServer::create([
            'name' => 'modern-pg16-server',
            'hostname' => '10.0.0.2',
            'web_server' => 'nginx',
            'postgres_port' => 5432,
            'is_active' => true,
        ]);

        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';
        $filePath = '/var/lib/postgresql/16/main/base/16384/24581';

        DiskFileSnapshot::create([
            'server_id' => $server2->id,
            'directory_path' => dirname($filePath),
            'file_path' => $filePath,
            'size_bytes' => 1073741824,
            'snapshot_at' => $targetDate,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $expectedCmd = $builder->build($server2, 'SELECT oid, datname FROM pg_database;');

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->once())
            ->method('execute')
            ->with($server2, $expectedCmd)
            ->willReturn(new RemoteCommandResult(true, "16384|sip_mutif_db\n13408|postgres", null));

        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);
        $result = $service->getFileGrowth($server2, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('sip_mutif_db', $result['files'][0]['database_name']);
    }

    public function test_server_without_postgres_port_returns_unknown_database_without_running_ssh()
    {
        $serverNoPg = MonitoredServer::create([
            'name' => 'web-only-server',
            'hostname' => '10.0.0.3',
            'web_server' => 'nginx',
            'postgres_port' => 0,
            'is_active' => true,
        ]);

        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';
        $filePath = '/var/lib/postgresql/16/main/base/16384/24581';

        DiskFileSnapshot::create([
            'server_id' => $serverNoPg->id,
            'directory_path' => dirname($filePath),
            'file_path' => $filePath,
            'size_bytes' => 50000000,
            'snapshot_at' => $targetDate,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->never())->method('execute');

        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);
        $result = $service->getFileGrowth($serverNoPg, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('Unknown', $result['files'][0]['database_name']);
    }

    public function test_non_postgresql_path_does_not_have_database_name()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/log/nginx';
        $filePath = '/var/log/nginx/access.log';

        DiskFileSnapshot::create([
            'server_id' => $this->server->id,
            'directory_path' => $directory,
            'file_path' => $filePath,
            'size_bytes' => 5000000,
            'snapshot_at' => $targetDate,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->never())->method('execute');

        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);
        $result = $service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertNull($result['files'][0]['database_name']);
    }

    public function test_unmapped_postgresql_oid_defaults_to_unknown()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';
        $filePath = '/var/lib/postgresql/18/main/base/99999/1234';

        DiskFileSnapshot::create([
            'server_id' => $this->server->id,
            'directory_path' => dirname($filePath),
            'file_path' => $filePath,
            'size_bytes' => 50000000,
            'snapshot_at' => $targetDate,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->once())
            ->method('execute')
            ->willReturn(new RemoteCommandResult(true, "26965|exapro_mutif_02052021_2\n13408|postgres", null));

        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);
        $result = $service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('Unknown', $result['files'][0]['database_name']);
    }

    public function test_ssh_failure_during_oid_fetch_defaults_gracefully_to_unknown()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';
        $filePath = '/var/lib/postgresql/18/main/base/26965/27361';

        DiskFileSnapshot::create([
            'server_id' => $this->server->id,
            'directory_path' => dirname($filePath),
            'file_path' => $filePath,
            'size_bytes' => 50000000,
            'snapshot_at' => $targetDate,
        ]);

        $builder = new PostgreSqlCommandBuilder();
        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('SSH connection timeout'));

        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);
        $result = $service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertNotEmpty($result['files']);
        $this->assertEquals('Unknown', $result['files'][0]['database_name']);
    }

    public function test_postgresql_database_growth_positive_zero_and_new_database()
    {
        $prevDate = Carbon::parse('2026-09-09 12:00:00');
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        // DB 1: Growing database (100 MB -> 500 MB = +400 MB)
        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10001', 'database_name' => 'db_growing', 'size_bytes' => 100000000, 'snapshot_at' => $prevDate]);
        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10001', 'database_name' => 'db_growing', 'size_bytes' => 500000000, 'snapshot_at' => $targetDate]);

        // DB 2: Static database (50 MB -> 50 MB = 0 B)
        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10002', 'database_name' => 'db_static', 'size_bytes' => 50000000, 'snapshot_at' => $prevDate]);
        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10002', 'database_name' => 'db_static', 'size_bytes' => 50000000, 'snapshot_at' => $targetDate]);

        // DB 3: Brand new database created on target date (0 B -> 200 MB = +200 MB)
        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10003', 'database_name' => 'db_new', 'size_bytes' => 200000000, 'snapshot_at' => $targetDate]);

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->method('execute')->willReturn(new RemoteCommandResult(false, '', null));
        $builder = new PostgreSqlCommandBuilder();
        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);

        $result = $service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertArrayHasKey('databases', $result);
        $this->assertCount(3, $result['databases']);

        // Sorted by growth DESC: db_growing (+400 MB) -> db_new (+200 MB) -> db_static (0 B)
        $db1 = $result['databases'][0];
        $this->assertEquals('db_growing', $db1['databaseName']);
        $this->assertEquals(400000000, $db1['growthBytes']);

        $db2 = $result['databases'][1];
        $this->assertEquals('db_new', $db2['databaseName']);
        $this->assertEquals(0, $db2['previousSizeBytes']);
        $this->assertEquals('0 B', $db2['previousSizeFormatted']);
        $this->assertEquals(200000000, $db2['growthBytes']);

        $db3 = $result['databases'][2];
        $this->assertEquals('db_static', $db3['databaseName']);
        $this->assertEquals(50000000, $db3['previousSizeBytes']);
        $this->assertEquals(0, $db3['growthBytes']);
    }

    public function test_postgresql_database_growth_no_baseline_returns_null_previous_and_growth()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');
        $directory = '/var/lib/postgresql';

        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10001', 'database_name' => 'db_first_time', 'size_bytes' => 500000000, 'snapshot_at' => $targetDate]);

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->method('execute')->willReturn(new RemoteCommandResult(false, '', null));
        $builder = new PostgreSqlCommandBuilder();
        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);

        $result = $service->getFileGrowth($this->server, '2026-09-10', $directory);

        $this->assertArrayHasKey('databases', $result);
        $this->assertCount(1, $result['databases']);
        $this->assertNull($result['databases'][0]['previousSizeBytes']);
        $this->assertNull($result['databases'][0]['growthBytes']);
        $this->assertEquals('N/A', $result['databases'][0]['previousSizeFormatted']);
        $this->assertEquals('N/A', $result['databases'][0]['growthFormatted']);
    }

    public function test_postgresql_database_growth_only_returned_for_postgresql_directory()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        PostgresqlDatabaseSizeSnapshot::create(['server_id' => $this->server->id, 'database_oid' => '10001', 'database_name' => 'db_test', 'size_bytes' => 500000000, 'snapshot_at' => $targetDate]);

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->method('execute')->willReturn(new RemoteCommandResult(false, '', null));
        $builder = new PostgreSqlCommandBuilder();
        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);

        // Non-postgresql directory like /var/log/nginx
        $result = $service->getFileGrowth($this->server, '2026-09-10', '/var/log/nginx');

        $this->assertArrayHasKey('databases', $result);
        $this->assertEmpty($result['databases']);
    }

    public function test_large_dataset_postgresql_database_growth_memory_efficiency()
    {
        $baseDate = Carbon::parse('2026-09-10 12:00:00');

        // Insert 10,000 database snapshot rows in bulk chunks across 3 days
        $rows = [];
        for ($day = 0; $day < 3; $day++) {
            $dayDate = $baseDate->copy()->subDays($day);
            for ($run = 0; $run < 100; $run++) {
                $snapshotAt = $dayDate->copy()->subMinutes($run * 2);
                for ($db = 1; $db <= 35; $db++) {
                    $rows[] = [
                        'server_id' => $this->server->id,
                        'database_oid' => (string) (10000 + $db),
                        'database_name' => "app_db_{$db}",
                        'size_bytes' => 100000000 + ((2 - $day) * 10000000) + ($db * 100000),
                        'snapshot_at' => $snapshotAt->toDateTimeString(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('postgresql_database_size_snapshots')->insert($chunk);
        }

        $mockCommands = $this->createMock(RemoteCommandService::class);
        $mockCommands->method('execute')->willReturn(new RemoteCommandResult(false, '', null));
        $builder = new PostgreSqlCommandBuilder();
        $service = new DiskGrowthDetailService(new DiskStorageGrowthService(), $mockCommands, $builder);

        $startMemory = memory_get_usage();

        $result = $service->getFileGrowth($this->server, '2026-09-10', '/var/lib/postgresql');

        $endMemory = memory_get_usage();
        $memoryDelta = $endMemory - $startMemory;

        $this->assertNotEmpty($result['databases']);
        // Returns strictly capped top 10 databases by growth
        $this->assertCount(10, $result['databases']);
        // Memory delta must be minimal (< 3 MB) due to database-side LIMIT and JOIN
        $this->assertLessThan(3 * 1024 * 1024, $memoryDelta);
    }
}
