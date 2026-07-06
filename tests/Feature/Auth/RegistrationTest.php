<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // New users have no workspace yet; the dashboard hands them to onboarding.
        $this->get(route('dashboard'))->assertRedirect(route('onboarding.show', absolute: false));
    }

    public function test_registration_screen_redirects_to_login_when_invite_only_after_first_user(): void
    {
        User::factory()->create();

        $this->get('/register')
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'Registration is invite-only. Use an invite link or sign in.');
    }

    public function test_home_redirects_guest_to_register_before_setup(): void
    {
        $this->get('/')->assertRedirect(route('register', absolute: false));
    }

    public function test_home_redirects_guest_to_login_after_setup(): void
    {
        User::factory()->create();

        $this->get('/')->assertRedirect(route('login', absolute: false));
    }
}
