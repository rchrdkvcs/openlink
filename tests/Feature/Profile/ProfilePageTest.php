<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesSocialAccounts;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use CreatesSocialAccounts;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_page_keeps_workspace_selection_available(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingInWorkspace($user, $current)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('currentWorkspace.id', $current->id)
                ->has('workspaces', 2)
                ->where('workspaces.0.id', $current->id)
                ->where('workspaces.1.id', $other->id));
    }

    public function test_profile_page_includes_connected_identities_avatar_source_and_api_tokens(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $account = $this->googleAccount($user, ['avatar_url' => 'https://cdn.example.test/ada.png']);
        $user->forceFill(['profile_avatar_social_account_id' => $account->id])->save();
        $user->createToken('Browser extension');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('profileAvatar.url', 'https://cdn.example.test/ada.png')
                ->where('connectedIdentities.0.is_valid', true)
                ->where('connectedIdentities.0.is_avatar_source', true)
                ->where('apiTokens.0.name', 'Browser extension')
            );
    }
}
