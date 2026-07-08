<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_page_includes_connected_identities_avatar_source_and_api_tokens(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'avatar_url' => 'https://cdn.example.test/ada.png',
        ]);
        $user->forceFill(['profile_avatar_social_account_id' => $account->id])->save();
        $user->createToken('Browser extension');

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('profileAvatar.url', 'https://cdn.example.test/ada.png')
                ->where('connectedIdentities.0.is_valid', true)
                ->where('connectedIdentities.0.is_avatar_source', true)
                ->where('apiTokens.0.name', 'Browser extension')
            );
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_changing_email_sends_verification_and_invalidates_avatar_source(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
            'email' => 'old@example.com',
            'email_verified' => true,
            'avatar_url' => 'https://cdn.example.test/old.png',
        ]);
        $user->forceFill(['profile_avatar_social_account_id' => $account->id])->save();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'new@example.com',
            ])
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->profile_avatar_social_account_id);
    }

    public function test_profile_avatar_can_be_selected_from_a_valid_connected_identity(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'avatar_url' => 'https://cdn.example.test/ada.png',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.avatar.update'), [
                'profile_avatar_social_account_id' => $account->id,
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'profile']));

        $this->assertSame($account->id, $user->refresh()->profile_avatar_social_account_id);
    }

    public function test_api_tokens_can_be_created_and_revoked_from_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('profile.api-tokens.store'), ['name' => 'CLI']);

        $response->assertRedirect(route('profile.edit', ['tab' => 'api-tokens']));
        $response->assertSessionHas('newApiToken.token');
        $this->assertSame('CLI', $user->tokens()->first()->name);

        $this->actingAs($user)
            ->delete(route('profile.api-tokens.destroy', $user->tokens()->first()->id))
            ->assertRedirect(route('profile.edit', ['tab' => 'api-tokens']));

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_api_tokens_cannot_be_created_until_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('profile.api-tokens.store'), ['name' => 'CLI'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_connected_identity_unlink_requires_one_remaining_sign_in_method(): void
    {
        $user = User::factory()->create([
            'email' => 'oauth@example.com',
            'password' => null,
        ]);
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
            'email' => 'oauth@example.com',
            'email_verified' => true,
            'avatar_url' => 'https://cdn.example.test/avatar.png',
        ]);

        $this->actingAs($user)
            ->delete(route('profile.connected-identities.destroy', $account))
            ->assertSessionHasErrors('identity');

        $this->assertNotNull($account->fresh());

        $user->forceFill(['password' => Hash::make('password')])->save();

        $this->actingAs($user)
            ->delete(route('profile.connected-identities.destroy', $account))
            ->assertRedirect(route('profile.edit', ['tab' => 'connected-identities']));

        $this->assertNull($account->fresh());
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
