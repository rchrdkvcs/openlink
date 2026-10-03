<?php

namespace Tests\Feature\Auth\OAuth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
