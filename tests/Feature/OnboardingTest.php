<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_workspace_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.show', absolute: false));
        $this->actingAs($user)->get('/members')->assertRedirect(route('onboarding.show', absolute: false));
        $this->actingAs($user)->get(route('onboarding.show'))->assertOk();
    }

    public function test_onboarding_creates_the_workspace_and_continues_the_wizard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.workspace'), ['name' => 'Acme'])
            ->assertRedirect(route('onboarding.show', absolute: false));

        $workspace = Workspace::query()->where('name', 'Acme')->firstOrFail();
        $this->assertSame($user->id, $workspace->owner_id);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        // The wizard stays reachable for the optional steps…
        $this->actingAs($user)->get(route('onboarding.show'))->assertOk();

        // …until the user completes it.
        $this->actingAs($user)
            ->post(route('onboarding.complete'))
            ->assertRedirect(route('dashboard', absolute: false));
        $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_onboarding_redirects_users_who_already_have_a_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Existing',
            'slug' => 'existing',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        $this->actingAs($user)->get(route('onboarding.show'))->assertRedirect(route('dashboard', absolute: false));

        // A second workspace cannot be created through onboarding.
        $this->actingAs($user)
            ->post(route('onboarding.workspace'), ['name' => 'Another'])
            ->assertRedirect(route('onboarding.show', absolute: false));
        $this->assertDatabaseMissing('workspaces', ['name' => 'Another']);
    }
}
