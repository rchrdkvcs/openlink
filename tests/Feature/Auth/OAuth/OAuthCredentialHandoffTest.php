<?php

namespace Tests\Feature\Auth\OAuth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FakesOAuthProviders;
use Tests\TestCase;

class OAuthCredentialHandoffTest extends TestCase
{
    use FakesOAuthProviders;
    use RefreshDatabase;

    public function test_oauth_sends_two_factor_users_to_the_existing_challenge(): void
    {
        $this->configureGoogle();
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create([
            'email' => 'two-factor@example.com',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->mockSocialiteUser('google', [
            'id' => 'google-9',
            'email' => 'two-factor@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback()->assertRedirect(route('login.two-factor', absolute: false));

        $this->assertGuest();
        $this->assertSame($user->id, session('login.two_factor.user_id'));

        $this->post(route('login.two-factor'), [
            'one_time_password' => $google2fa->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_oauth_only_user_cannot_login_with_password_until_password_is_set_by_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'oauth-only@example.com',
            'password' => null,
        ]);

        $this->post(route('login'), [
            'email' => 'oauth-only@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('password.email'), ['email' => 'oauth-only@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
