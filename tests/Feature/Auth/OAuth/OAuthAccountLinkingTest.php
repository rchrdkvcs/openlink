<?php

namespace Tests\Feature\Auth\OAuth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesSocialAccounts;
use Tests\Support\FakesOAuthProviders;
use Tests\TestCase;

class OAuthAccountLinkingTest extends TestCase
{
    use CreatesSocialAccounts;
    use FakesOAuthProviders;
    use RefreshDatabase;

    public function test_oauth_auto_links_existing_account_only_when_provider_email_is_verified(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['email' => 'verified@example.com']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-6',
            'email' => 'verified@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-6',
        ]);
    }

    public function test_authenticated_user_can_connect_provider_from_profile(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['email' => 'profile@example.com']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-profile',
            'email' => 'profile@example.com',
            'email_verified' => true,
            'avatar' => 'https://cdn.example.test/profile.png',
        ]);

        $this->actingAs($user)
            ->googleCallback($this->profileLinkContext($user))
            ->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']));

        $account = SocialAccount::query()->where('provider_user_id', 'google-profile')->firstOrFail();

        $this->assertSame($user->id, $account->user_id);
        $this->assertSame($account->id, $user->refresh()->profile_avatar_social_account_id);
    }

    public static function providers(): array
    {
        return [
            'google' => ['google', 'email_verified'],
            'discord' => ['discord', 'verified'],
        ];
    }

    #[DataProvider('providers')]
    public function test_oauth_finds_existing_user_when_email_case_differs(string $provider, string $verifiedKey): void
    {
        $this->configureGoogle();
        $this->configureDiscord();
        $user = User::factory()->create(['email' => 'Profile@Example.com']);
        $this->mockSocialiteUser($provider, [
            'id' => 'normalized-sign-in',
            'email' => ' profile@EXAMPLE.COM ',
            $verifiedKey => true,
        ]);

        $this->withSession(['oauth.context' => ['provider' => $provider]])
            ->get(route('oauth.callback', ['provider' => $provider]))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => $provider,
            'email' => 'profile@example.com',
        ]);
    }

    #[DataProvider('providers')]
    public function test_connecting_provider_displays_identity_when_email_case_and_surrounding_spaces_differ(string $provider, string $verifiedKey): void
    {
        $this->configureGoogle();
        $this->configureDiscord();
        $user = User::factory()->create(['email' => 'Profile@Example.com']);
        $this->mockSocialiteUser($provider, [
            'id' => 'normalized-profile',
            'email' => ' profile@EXAMPLE.COM ',
            $verifiedKey => true,
            'avatar' => 'https://cdn.example.test/profile.png',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['oauth.context' => ['provider' => $provider, ...$this->profileLinkContext($user)]])
            ->get(route('oauth.callback', ['provider' => $provider]));

        $response->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']));

        $this->actingAs($user->fresh())->get($response->headers->get('Location'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->has('connectedIdentities', 1)
                ->where('connectedIdentities.0.email', 'profile@example.com')
                ->where('connectedIdentities.0.is_valid', true)
                ->where('profileAvatar.url', 'https://cdn.example.test/profile.png')
            );

        $this->assertSame(2, $user->fresh()->validSignInMethodCount());
    }

    #[DataProvider('providers')]
    public function test_connecting_provider_displays_identity_when_local_email_is_not_yet_verified(string $provider, string $verifiedKey): void
    {
        $this->configureGoogle();
        $this->configureDiscord();
        $user = User::factory()->unverified()->create(['email' => 'profile@example.com']);
        $this->mockSocialiteUser($provider, [
            'id' => 'provider-profile',
            'email' => $user->email,
            $verifiedKey => true,
            'avatar' => 'https://cdn.example.test/profile.png',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['oauth.context' => ['provider' => $provider, ...$this->profileLinkContext($user)]])
            ->get(route('oauth.callback', ['provider' => $provider]));

        $response->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']))
            ->assertSessionHas('status', 'Connected identity added.');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->actingAs($user->fresh())->get($response->headers->get('Location'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->has('connectedIdentities', 1)
                ->where('connectedIdentities.0.provider', $provider)
                ->where('connectedIdentities.0.email', $user->email)
                ->where('connectedIdentities.0.is_valid', true)
            );
    }

    #[DataProvider('providers')]
    public function test_connecting_provider_does_not_verify_local_email_when_provider_email_is_unverified(string $provider, string $verifiedKey): void
    {
        $this->configureGoogle();
        $this->configureDiscord();
        $user = User::factory()->unverified()->create(['email' => 'profile@example.com']);
        $this->mockSocialiteUser($provider, [
            'id' => 'unverified-profile',
            'email' => $user->email,
            $verifiedKey => false,
        ]);

        $this->actingAs($user)
            ->withSession(['oauth.context' => ['provider' => $provider, ...$this->profileLinkContext($user)]])
            ->get(route('oauth.callback', ['provider' => $provider]))
            ->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']))
            ->assertSessionHas('status', 'This provider did not return a verified email address.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'unverified-profile']);
    }

    #[DataProvider('providers')]
    public function test_connecting_provider_does_not_verify_local_email_when_provider_email_differs(string $provider, string $verifiedKey): void
    {
        $this->configureGoogle();
        $this->configureDiscord();
        $user = User::factory()->unverified()->create(['email' => 'profile@example.com']);
        $this->mockSocialiteUser($provider, [
            'id' => 'mismatched-profile',
            'email' => 'other@example.com',
            $verifiedKey => true,
        ]);

        $this->actingAs($user)
            ->withSession(['oauth.context' => ['provider' => $provider, ...$this->profileLinkContext($user)]])
            ->get(route('oauth.callback', ['provider' => $provider]))
            ->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']))
            ->assertSessionHas('status', 'This provider email must match your Openlink email address.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'mismatched-profile']);
    }

    public function test_connecting_provider_from_profile_requires_matching_verified_email(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['email' => 'profile@example.com']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-mismatch',
            'email' => 'other@example.com',
            'email_verified' => true,
        ]);

        $this->actingAs($user)
            ->googleCallback($this->profileLinkContext($user))
            ->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']))
            ->assertSessionHas('status', 'This provider email must match your Openlink email address.');

        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'google-mismatch']);
    }

    public function test_oauth_refuses_provider_identity_already_linked_to_another_email_account(): void
    {
        $this->configureGoogle();
        $linkedUser = User::factory()->create(['email' => 'linked@example.com']);
        User::factory()->create(['email' => 'other@example.com']);
        $this->googleAccount($linkedUser, ['provider_user_id' => 'google-8']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-8',
            'email' => 'other@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This sign-in method is already linked to another account.');

        $this->assertGuest();
    }

    private function profileLinkContext(User $user): array
    {
        return [
            'intent' => 'link',
            'invite_token' => null,
            'url_intended' => null,
            'user_id' => $user->id,
        ];
    }
}
