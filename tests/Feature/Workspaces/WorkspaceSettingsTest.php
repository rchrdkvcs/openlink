<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class WorkspaceSettingsTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_workspace_can_be_created_with_icon_and_color(): void
    {
        $user = User::factory()->create();
        $this->workspaceFor($user, 'First', 'first');

        $this->actingAs($user)
            ->post(route('workspaces.store'), [
                'name' => 'Acme Events',
                'icon' => 'megaphone',
                'color' => 'teal',
            ])
            ->assertRedirect();

        $workspace = Workspace::query()->where('name', 'Acme Events')->firstOrFail();

        $this->assertSame('megaphone', $workspace->icon);
        $this->assertSame('teal', $workspace->color);
        $this->assertSame($workspace->id, session('workspace_id'));
    }

    public function test_owner_can_update_a_non_current_workspace_by_id(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingInWorkspace($user, $current)
            ->patch(route('workspaces.update', $other), [
                'name' => 'Renamed',
                'icon' => 'rocket',
                'color' => 'blue',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workspaces', [
            'id' => $other->id,
            'name' => 'Renamed',
            'icon' => 'rocket',
            'color' => 'blue',
        ]);
        $this->assertSame($current->id, session('workspace_id'));
    }

    public function test_non_manager_cannot_update_a_workspace(): void
    {
        [$workspace] = $this->workspaceWithOwner('Events', 'events');
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAs($viewer)
            ->patch(route('workspaces.update', $workspace), ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_workspace_update_rejects_unknown_icon_and_color(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, 'Events', 'events');

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->patch(route('workspaces.update', $workspace), [
                'name' => 'Events',
                'icon' => 'not-an-icon',
                'color' => '#ff0000',
            ])
            ->assertSessionHasErrors(['icon', 'color']);
    }

    public function test_manager_can_load_manage_payload_for_any_of_their_workspaces(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');
        $other->update(['icon' => 'star', 'color' => 'pink']);

        $this->actingInWorkspace($user, $current)
            ->getJson(route('workspaces.manage', $other))
            ->assertOk()
            ->assertJson([
                'id' => $other->id,
                'name' => 'Other',
                'icon' => 'star',
                'color' => 'pink',
                'role' => WorkspaceMember::ROLE_OWNER,
                'can_delete' => true,
            ]);
    }

    public function test_non_manager_cannot_load_manage_payload(): void
    {
        [$workspace] = $this->workspaceWithOwner('Events', 'events');
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAs($viewer)
            ->getJson(route('workspaces.manage', $workspace))
            ->assertForbidden();
    }
}
