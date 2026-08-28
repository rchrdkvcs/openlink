<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_a_non_current_workspace_by_id(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingAs($user)
            ->withSession(['workspace_id' => $current->id])
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
        $owner = User::factory()->create();
        $workspace = $this->workspaceFor($owner, 'Events', 'events');

        $viewer = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);

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

        $this->actingAs($user)
            ->withSession(['workspace_id' => $current->id])
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
        $owner = User::factory()->create();
        $workspace = $this->workspaceFor($owner, 'Events', 'events');

        $viewer = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);

        $this->actingAs($viewer)
            ->getJson(route('workspaces.manage', $workspace))
            ->assertForbidden();
    }

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

    public function test_switching_workspace_leaves_a_resource_page_and_refreshes_following_pages(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingAs($user)
            ->withSession(['workspace_id' => $current->id])
            ->from('/qr-codes/old-workspace-code')
            ->post(route('workspaces.switch', $other), ['destination' => 'qr-codes.index'])
            ->assertRedirect(route('qr-codes.index'));

        $this->assertSame($other->id, session('workspace_id'));

        foreach (['dashboard', 'links.index', 'qr-codes.index', 'analytics.index', 'domains.index', 'members.index'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('currentWorkspace.id', $other->id)
                    ->where('currentWorkspace.name', 'Other'));
        }
    }

    public function test_deleting_a_non_current_workspace_keeps_the_current_selection(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingAs($user)
            ->withSession(['workspace_id' => $current->id])
            ->delete(route('workspaces.destroy', $other))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('workspaces', ['id' => $other->id]);
        $this->assertSame($current->id, session('workspace_id'));
    }

    public function test_invite_link_creation_returns_json_payload_for_json_requests(): void
    {
        $user = User::factory()->create();
        $this->workspaceFor($user, 'Current', 'current');
        $created = $this->workspaceFor($user, 'Created', 'created');

        $response = $this->actingAs($user)
            ->withHeader('X-Workspace-Id', (string) $created->id)
            ->postJson(route('invite-links.store'), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertCreated()
            ->assertJsonStructure(['id', 'role', 'token', 'url']);

        $this->assertDatabaseHas('invite_links', [
            'workspace_id' => $created->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertSame(WorkspaceMember::ROLE_EDITOR, $response->json('role'));
    }

    private function workspaceFor(User $user, string $name, string $slug): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $name,
            'slug' => $slug,
            'settings' => [],
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        return $workspace;
    }
}
