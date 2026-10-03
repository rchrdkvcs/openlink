<?php

namespace Tests\Feature\Auth\OAuth;

use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\FakesOAuthProviders;
use Tests\TestCase;

class OAuthProviderAvailabilityTest extends TestCase
{
    use CreatesWorkspaces;
    use FakesOAuthProviders;
    use RefreshDatabase;

    public function test_configured_providers_appear_on_login_and_register_when_registration_is_allowed(): void
    {
        $this->configureGoogle();
        $this->configureDiscord();

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('oauthProviders.google', true)
            ->where('oauthProviders.discord', true)
        );

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('oauthProviders.google', true)
        );

        User::factory()->create();
        app(InstanceSettings::class)->set('registration_mode', 'open');

        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('oauthProviders.google', true)
        );
    }

    public function test_configured_providers_appear_on_register_with_a_valid_invite_link(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner);

        $this->get(route('register', ['invite' => $inviteLink->token]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Register')
                ->where('oauthProviders.google', true)
                ->where('invite.token', $inviteLink->token)
            );
    }

    public function test_unconfigured_providers_do_not_appear_and_redirect_is_refused(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('oauthProviders', [])
        );

        $this->get(route('oauth.redirect', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This sign-in method is not available.');
    }

    public function test_oauth_redirect_uses_minimal_scopes_and_stores_context(): void
    {
        $this->configureGoogle();
        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->once()->with(['openid', 'profile', 'email'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.example.test/oauth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);

        $this->get(route('oauth.redirect', [
            'provider' => 'google',
            'intent' => 'register',
            'invite' => 'invite-token',
        ]))->assertRedirect('https://accounts.example.test/oauth');

        $this->assertSame([
            'provider' => 'google',
            'intent' => 'register',
            'invite_token' => 'invite-token',
            'url_intended' => null,
        ], session('oauth.context'));
    }

    public function test_oauth_callback_is_not_accepted_on_redirect_only_hosts(): void
    {
        $this->configureGoogle();

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get('http://links.example.com/auth/google/callback')
            ->assertNotFound();

        $this->assertGuest();
    }
}
