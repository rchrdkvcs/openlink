<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class CurrentWorkspaceSelectionTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_switching_workspace_leaves_a_resource_page_and_refreshes_following_pages(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingInWorkspace($user, $current)
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

    public function test_workspace_manager_can_delete_current_workspace_and_switch_to_another(): void
    {
        [$workspace, , $user] = $this->workspaceWithActiveDomain();
        $otherWorkspace = $this->workspaceFor($user, 'Other', 'other');

        $this->actingInWorkspace($user, $workspace)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertSame($otherWorkspace->id, session('workspace_id'));
    }

    public function test_deleting_a_non_current_workspace_keeps_the_current_selection(): void
    {
        $user = User::factory()->create();
        $current = $this->workspaceFor($user, 'Current', 'current');
        $other = $this->workspaceFor($user, 'Other', 'other');

        $this->actingInWorkspace($user, $current)
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
}
