<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GoogleSsoAuthTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a dummy user to satisfy application_request_logs foreign key constraint
        // because auth()->id() will now map to authorized_emails but the log table
        // expects it in the users table.
        \App\Models\User::factory()->create(['id' => 1]);
    }

    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');

        $response = $this->get('/servers');
        $response->assertRedirect('/login');
    }

    public function test_authorized_user_can_login()
    {
        \App\Models\AuthorizedEmail::factory()->create([
            'email' => 'devanlustig@gmail.com',
            'is_active' => true,
        ]);

        $abstractUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('DevanLustig@gmail.com');
        
        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        
        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_unauthorized_user_cannot_login()
    {
        $abstractUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('unauthorized@gmail.com');
        
        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
        $response->assertSessionHas('error');
    }

    public function test_inactive_authorized_user_cannot_login()
    {
        \App\Models\AuthorizedEmail::factory()->create([
            'email' => 'inactive@gmail.com',
            'is_active' => false,
        ]);

        $abstractUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('inactive@gmail.com');
        
        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
        $response->assertSessionHas('error');
    }

    public function test_authenticated_user_can_logout()
    {
        $user = \App\Models\AuthorizedEmail::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');
        
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
