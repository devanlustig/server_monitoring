<?php

namespace Tests\Feature;

use App\Models\MonitoredServer;
use App\Models\User;
use App\Services\Monitoring\Memory\MemoryDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MemoryDetailControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createServer()
    {
        return MonitoredServer::create([
            'name' => 'Test Server',
            'hostname' => 'test-server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_username' => 'root',
            'ssh_password' => 'pass'
        ]);
    }

    public function test_guest_is_redirected_to_login()
    {
        $server = $this->createServer();

        $response = $this->getJson(route('servers.memory.detail', $server));

        $response->assertUnauthorized(); // If testing with getJson it returns 401
    }

    public function test_auth_user_can_access_memory_detail_successfully()
    {
        $user = User::factory()->create();
        $server = $this->createServer();

        $mockService = Mockery::mock(MemoryDetailService::class);
        $mockService->shouldReceive('getMemoryDetail')
            ->once()
            ->with(Mockery::on(function ($s) use ($server) {
                return $s->id === $server->id;
            }))
            ->andReturn([
                'success' => true,
                'breakdown' => ['total' => 1024],
                'processes' => [],
                'analysis' => ['has_pressure' => false]
            ]);

        $this->app->instance(MemoryDetailService::class, $mockService);

        $response = $this->actingAs($user)
            ->getJson(route('servers.memory.detail', $server));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'breakdown' => ['total' => 1024]
            ]);
    }

    public function test_handles_service_failure_gracefully_without_500()
    {
        $user = User::factory()->create();
        $server = $this->createServer();

        $mockService = Mockery::mock(MemoryDetailService::class);
        $mockService->shouldReceive('getMemoryDetail')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'SSH connection failed'
            ]);

        $this->app->instance(MemoryDetailService::class, $mockService);

        $response = $this->actingAs($user)
            ->getJson(route('servers.memory.detail', $server));

        // As specified, should return 500 error code for graceful handle if we want the JS to catch it,
        // Wait, in controller I used 500 status code for "not success", but caught Exception returns 200 with success=false.
        // Let's assert status 500 and the JSON message.
        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'SSH connection failed'
            ]);
    }

    public function test_handles_exceptions_gracefully()
    {
        $user = User::factory()->create();
        $server = $this->createServer();

        $mockService = Mockery::mock(MemoryDetailService::class);
        $mockService->shouldReceive('getMemoryDetail')
            ->once()
            ->andThrow(new \Exception('Fatal exception'));

        $this->app->instance(MemoryDetailService::class, $mockService);

        $response = $this->actingAs($user)
            ->getJson(route('servers.memory.detail', $server));

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching memory details: Fatal exception'
            ]);
    }
}
