<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesSocialAccounts;
use Tests\TestCase;

class ProfileSignInMethodsTest extends TestCase
{
    use CreatesSocialAccounts;
    use RefreshDatabase;

    public function test_profile_avatar_can_be_selected_from_a_valid_connected_identity(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $account = $this->googleAccount($user, ['avatar_url' => 'https://cdn.example.test/ada.png']);

        $this->actingAs($user)
            ->patch(route('profile.avatar.update'), [
                'profile_avatar_social_account_id' => $account->id,
            ])
            ->assertRedirect(route('profile.edit', ['tab' => 'profile']));

        $this->assertSame($account->id, $user->refresh()->profile_avatar_social_account_id);
    }

    public function test_connected_identity_unlink_requires_one_remaining_sign_in_method(): void
    {
        $user = User::factory()->create([
            'email' => 'oauth@example.com',
            'password' => null,
        ]);
        $account = $this->googleAccount($user, ['avatar_url' => 'https://cdn.example.test/avatar.png']);

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
        app(InstanceSettings::class)->set('require_email_verification', true);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('profile.api-tokens.store'), ['name' => 'CLI'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $user->tokens()->count());
    }
}
