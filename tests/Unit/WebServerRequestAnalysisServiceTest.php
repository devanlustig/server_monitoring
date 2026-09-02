<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MonitoredServer;
use App\Services\Monitoring\Analytics\WebServerRequestAnalysisService;
use App\Services\Monitoring\Collectors\ApacheCollector;
use App\Services\Monitoring\Collectors\NginxCollector;
use App\Services\Monitoring\EndpointSourceResolverFactory;
use App\Services\Monitoring\WebServerEndpointResolver;
use App\Services\Monitoring\DTO\ApacheLogEntryData;
use App\Services\Monitoring\DTO\WebEndpointSourceData;
use DateTimeImmutable;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebServerRequestAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyzes_nginx_requests_within_timestamp_window()
    {
        $server = MonitoredServer::create([
            'name' => 'nginx-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'nginx',
            'is_active' => true,
        ]);

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2026-09-02 10:00:00');

        $entries = [
            new ApacheLogEntryData(
                virtualHost: null,
                ip: '127.0.0.1',
                timestamp: '02/Sep/2026:10:00:00 +0000',
                dateTime: $dateTime,
                method: 'GET',
                endpoint: '/api/order',
                statusCode: 200,
                bytes: 1024,
                referer: null,
                userAgent: null,
                responseTimeMs: null,
            ),
            new ApacheLogEntryData(
                virtualHost: null,
                ip: '127.0.0.1',
                timestamp: '02/Sep/2026:10:00:05 +0000',
                dateTime: $dateTime,
                method: 'GET',
                endpoint: '/api/order',
                statusCode: 500,
                bytes: 512,
                referer: null,
                userAgent: null,
                responseTimeMs: null,
            ),
            new ApacheLogEntryData(
                virtualHost: null,
                ip: '127.0.0.1',
                timestamp: '02/Sep/2026:10:00:10 +0000',
                dateTime: $dateTime,
                method: 'GET',
                endpoint: '/login',
                statusCode: 200,
                bytes: 2048,
                referer: null,
                userAgent: null,
                responseTimeMs: null,
            ),
        ];

        $nginxCollector = Mockery::mock(NginxCollector::class);
        $nginxCollector->shouldReceive('collect')
            ->with($server)
            ->andReturn(['logFound' => true, 'entries' => $entries]);

        $apacheCollector = Mockery::mock(ApacheCollector::class);

        $resolver = Mockery::mock(WebServerEndpointResolver::class);
        $resolver->shouldReceive('resolve')
            ->andReturn([
                '/api/order' => new WebEndpointSourceData(
                    endpoint: '/api/order',
                    sourceType: 'proxy',
                    sourcePath: 'http://127.0.0.1:5601',
                    proxyTarget: 'http://127.0.0.1:5601',
                    virtualHost: null,
                    locationPattern: null,
                    resolved: true,
                ),
                '/login' => new WebEndpointSourceData(
                    endpoint: '/login',
                    sourceType: 'root',
                    sourcePath: '/var/www/html',
                    proxyTarget: null,
                    virtualHost: null,
                    locationPattern: null,
                    resolved: true,
                )
            ]);

        $factory = Mockery::mock(EndpointSourceResolverFactory::class);
        $factory->shouldReceive('make')
            ->with('nginx')
            ->andReturn($resolver);

        $service = new WebServerRequestAnalysisService($apacheCollector, $nginxCollector, $factory);

        $result = $service->analyze($server, '2026-09-02 10:00:00');

        $this->assertEquals('2026-09-02 10:00:00', $result['timestamp']);
        $this->assertEquals(3, $result['totalRequests']);
        $this->assertCount(2, $result['endpoints']);

        $topEp = $result['endpoints'][0];
        $this->assertEquals('/api/order', $topEp['endpoint']);
        $this->assertEquals(2, $topEp['requests']);
        $this->assertEquals(66.7, $topEp['percentage']);
        $this->assertEquals(1, $topEp['http2xx']);
        $this->assertEquals(1, $topEp['http5xx']);
        $this->assertEquals('proxy', $topEp['sourceType']);
        $this->assertEquals('http://127.0.0.1:5601', $topEp['application']);
    }

    public function test_returns_empty_response_when_no_logs_match_window()
    {
        $server = MonitoredServer::create([
            'name' => 'apache-server',
            'hostname' => '127.0.0.1',
            'web_server' => 'apache',
            'is_active' => true,
        ]);

        $apacheCollector = Mockery::mock(ApacheCollector::class);
        $apacheCollector->shouldReceive('collect')
            ->with($server)
            ->andReturn(['logFound' => true, 'entries' => []]);

        $nginxCollector = Mockery::mock(NginxCollector::class);
        $factory = Mockery::mock(EndpointSourceResolverFactory::class);

        $service = new WebServerRequestAnalysisService($apacheCollector, $nginxCollector, $factory);

        $result = $service->analyze($server, '2026-09-02 12:00:00');

        $this->assertEquals(0, $result['totalRequests']);
        $this->assertEmpty($result['endpoints']);
    }
}
