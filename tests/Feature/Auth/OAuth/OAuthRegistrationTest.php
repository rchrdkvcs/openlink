<?php

namespace Tests\Feature\Auth\OAuth;

use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakesOAuthProviders;
use Tests\TestCase;

class OAuthRegistrationTest extends TestCase
{
    use FakesOAuthProviders;
    use RefreshDatabase;

    public function test_oauth_creates_the_first_user_as_instance_admin(): void
    {
        Event::fake([Registered::class]);
        $this->configureGoogle();
        $this->mockSocialiteUser('google', [
            'id' => 'google-1',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback()->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->is_instance_admin);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('domains', ['hostname' => 'localhost', 'is_default' => true]);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ]);
        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($user));
    }

    public function test_oauth_creates_a_user_when_registration_is_open(): void
    {
        User::factory()->create();
        app(InstanceSettings::class)->set('registration_mode', 'open');
        $this->configureGoogle();
        $this->mockSocialiteUser('google', [
            'id' => 'google-2',
            'email' => 'open@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback()->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'open@example.com',
            'is_instance_admin' => false,
        ]);
    }

    public function test_oauth_refuses_to_create_a_user_in_invite_only_mode_without_invite(): void
    {
        User::factory()->create();
        $this->configureGoogle();
        $this->mockSocialiteUser('google', [
            'id' => 'google-3',
            'email' => 'blocked@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registration is not available. Use an invite link or sign in with an existing account.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_oauth_refuses_missing_or_unverified_provider_email(): void
    {
        User::factory()->create();
        $this->configureGoogle();
        $this->mockSocialiteUser('google', [
            'id' => 'google-7',
            'email' => 'unverified@example.com',
            'email_verified' => false,
        ]);

        $this->googleCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This provider did not return a verified email address.');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'google-7']);
    }

    public function test_oauth_refuses_provider_response_without_email(): void
    {
        User::factory()->create();
        $this->configureGoogle();
        $this->mockSocialiteUser('google', [
            'id' => 'google-no-email',
            'email_verified' => true,
        ]);

        $this->googleCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This provider did not return a verified email address.');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'google-no-email']);
    }
}
