<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
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
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertNull($user->email_verified_at);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registration_does_not_fail_when_verification_email_transport_fails(): void
    {
        Event::listen(MessageSending::class, function (): void {
            throw new TransportException('SMTP timed out.');
        });

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
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
