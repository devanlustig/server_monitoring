<?php

namespace Tests\Feature;

use App\Models\MonitoredServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Monitoring dashboard');
        $response->assertSee('No servers have been registered yet.');
    }

    public function test_the_cpu_dashboard_loads_without_samples(): void
    {
        $this->get('/cpu')
            ->assertOk()
            ->assertSee('CPU monitoring')
            ->assertSee('No CPU samples yet.');
    }

    public function test_the_server_management_pages_load(): void
    {
        $this->get('/servers')->assertOk()->assertSee('Monitored servers');
        $this->get('/servers/create')->assertOk()->assertSee('Test connection');
    }

    public function test_the_server_detail_page_displays_system_information(): void
    {
        $server = MonitoredServer::query()->create([
            'name' => 'Web server',
            'hostname' => 'web.example.test',
            'ssh_username' => 'monitor',
            'ssh_password' => 'secret',
            'system_hostname' => 'web-01',
            'operating_system' => 'Ubuntu 24.04',
            'cpu_core_count' => 4,
        ]);

        $this->get(route('servers.show', $server))
            ->assertOk()
            ->assertSee('System information')
            ->assertSee('Ubuntu 24.04')
            ->assertSee('web-01');
    }

    public function test_ssh_credentials_use_laravel_encryption(): void
    {
        $server = new MonitoredServer;
        $server->ssh_username = 'monitor';
        $server->ssh_password = 'sensitive-password';

        $this->assertNotSame('monitor', $server->getAttributes()['ssh_username']);
        $this->assertNotSame('sensitive-password', $server->getAttributes()['ssh_password']);
        $this->assertSame('monitor', $server->ssh_username);
        $this->assertSame('sensitive-password', $server->ssh_password);
    }
}
