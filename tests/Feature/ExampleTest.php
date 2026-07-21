<?php

namespace Tests\Feature;

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
}
