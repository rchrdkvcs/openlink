<?php

namespace Tests\Feature\Auth;

use App\Models\InviteLink;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OAuthAuthenticationTest extends TestCase
{
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

    public function test_oauth_creates_the_first_user_as_instance_admin(): void
    {
        $this->configureGoogle();
        $this->mockSocialiteUser('google', [
            'id' => 'google-1',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard', absolute: false));

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

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard', absolute: false));

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

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registration is not available. Use an invite link or sign in with an existing account.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_oauth_creates_a_user_and_joins_workspace_through_valid_invite_link(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);
        $this->mockSocialiteUser('google', [
            'id' => 'google-4',
            'email' => 'invited@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => 'google', 'invite_token' => $inviteLink->token]])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertSame(1, $inviteLink->fresh()->uses);
    }

    public function test_oauth_invite_link_cannot_create_a_user_when_registration_is_closed(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);
        app(InstanceSettings::class)->set('registration_mode', 'closed');
        $this->mockSocialiteUser('google', [
            'id' => 'google-closed',
            'email' => 'closed@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => 'google', 'invite_token' => $inviteLink->token]])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registration is not available. Use an invite link or sign in with an existing account.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'closed@example.com']);
        $this->assertSame(0, $inviteLink->fresh()->uses);
    }

    public function test_oauth_existing_user_joins_workspace_through_valid_invite_link(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_VIEWER);
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-5',
            'email' => 'existing@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => 'google', 'invite_token' => $inviteLink->token]])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);
    }

    public function test_oauth_auto_links_existing_account_only_when_provider_email_is_verified(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['email' => 'verified@example.com']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-6',
            'email' => 'verified@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-6',
        ]);
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

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
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

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This provider did not return a verified email address.');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'google-no-email']);
    }

    public function test_oauth_refuses_provider_identity_already_linked_to_another_email_account(): void
    {
        $this->configureGoogle();
        $linkedUser = User::factory()->create(['email' => 'linked@example.com']);
        User::factory()->create(['email' => 'other@example.com']);
        SocialAccount::create([
            'user_id' => $linkedUser->id,
            'provider' => 'google',
            'provider_user_id' => 'google-8',
            'email' => 'linked@example.com',
            'email_verified' => true,
        ]);
        $this->mockSocialiteUser('google', [
            'id' => 'google-8',
            'email' => 'other@example.com',
            'email_verified' => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This sign-in method is already linked to another account.');

        $this->assertGuest();
    }

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

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get(route('oauth.callback', ['provider' => 'google']))
            ->assertRedirect(route('login.two-factor', absolute: false));

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

    public function test_oauth_callback_is_not_accepted_on_redirect_only_hosts(): void
    {
        $this->configureGoogle();

        $this->withSession(['oauth.context' => ['provider' => 'google']])
            ->get('http://links.example.com/auth/google/callback')
            ->assertNotFound();

        $this->assertGuest();
    }

    /** @param array<string, mixed> $attributes */
    private function mockSocialiteUser(string $provider, array $attributes): void
    {
        $user = SocialiteUser::fake([
            'id' => $attributes['id'],
            'name' => $attributes['name'] ?? 'OAuth User',
            'email' => $attributes['email'] ?? null,
            'avatar' => $attributes['avatar'] ?? null,
            ...$attributes,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($user);

        Socialite::shouldReceive('driver')
            ->once()
            ->with($provider)
            ->andReturn($driver);
    }

    private function configureGoogle(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
    }

    private function configureDiscord(): void
    {
        config()->set('services.discord.client_id', 'discord-client');
        config()->set('services.discord.client_secret', 'discord-secret');
        config()->set('services.discord.redirect', 'http://localhost/auth/discord/callback');
    }

    /** @return array{Workspace, User} */
    private function workspaceWithOwner(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => 'Events',
            'slug' => 'events',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        return [$workspace, $owner];
    }

    private function inviteLink(Workspace $workspace, User $creator, string $role = WorkspaceMember::ROLE_EDITOR): InviteLink
    {
        return InviteLink::create([
            'workspace_id' => $workspace->id,
            'created_by_id' => $creator->id,
            'role' => $role,
            'token' => Str::random(48),
        ]);
    }
}
