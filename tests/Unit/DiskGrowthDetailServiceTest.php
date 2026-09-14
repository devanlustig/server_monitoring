<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Models\DiskDirectorySnapshot;
use App\Models\DiskFileSnapshot;
use App\Services\Monitoring\DiskGrowthDetailService;
use App\Services\Monitoring\DiskStorageGrowthService;
use App\Services\Monitoring\RemoteCommandService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'is_active' => true,
        ]);
        
        $storageGrowthService = new DiskStorageGrowthService();
        $commands = $this->createMock(RemoteCommandService::class);
        $this->service = new DiskGrowthDetailService($storageGrowthService, $commands);
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

        $this->assertNotEmpty($result['directories']);
        $this->assertEquals('/var/lib/postgresql', $result['directories'][0]['path']);
        $this->assertEquals(1000000000, $result['directories'][0]['previousSizeBytes']);
        $this->assertEquals(1500000000, $result['directories'][0]['growthBytes']);
    }

    public function test_directory_without_previous_snapshot_returns_null_previous_size_and_null_growth()
    {
        $targetDate = Carbon::parse('2026-09-10 12:00:00');

        // Target Snapshot exists, but NO previous snapshot exists on prior dates
        DiskDirectorySnapshot::create(['server_id' => $this->server->id, 'path' => '/var/lib/postgresql', 'size_bytes' => 87600000000, 'snapshot_at' => $targetDate]);

        $result = $this->service->getDirectoryGrowth($this->server, '2026-09-10');

        $this->assertNotEmpty($result['directories']);
        $this->assertEquals('/var/lib/postgresql', $result['directories'][0]['path']);
        // Must NOT default to 0 B or compute +87.6 GB fake growth!
        $this->assertNull($result['directories'][0]['previousSizeBytes']);
        $this->assertNull($result['directories'][0]['growthBytes']);
        $this->assertEquals('N/A', $result['directories'][0]['previousSizeFormatted']);
        $this->assertEquals('N/A', $result['directories'][0]['growthFormatted']);
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
}
