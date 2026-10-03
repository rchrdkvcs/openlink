<?php

namespace Tests\Feature\Members;

use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class MemberRoleTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_admin_can_change_a_member_role(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $membership = $this->membershipOf($workspace, $viewer);

        $this->actingInWorkspace($admin, $workspace)
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertRedirect();

        $this->assertSame(WorkspaceMember::ROLE_EDITOR, $membership->fresh()->role);
    }

    public function test_admin_members_page_exposes_member_management_controls(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingInWorkspace($admin, $workspace)
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageWorkspace', true)
                ->has('members', 2));
    }

    public function test_owner_role_cannot_be_changed_or_removed(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);
        $ownerMembership = $this->membershipOf($workspace, $owner);

        $this->actingInWorkspace($admin, $workspace)
            ->patch(route('members.update', $ownerMembership), ['role' => WorkspaceMember::ROLE_VIEWER])
            ->assertForbidden();

        $this->actingInWorkspace($admin, $workspace)
            ->delete(route('members.destroy', $ownerMembership))
            ->assertForbidden();
    }

    public function test_editor_cannot_manage_members(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $membership = $this->membershipOf($workspace, $viewer);

        $this->actingInWorkspace($editor, $workspace)
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertForbidden();
    }

    public function test_members_of_another_workspace_are_out_of_reach(): void
    {
        [, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $stranger = $this->memberOf($otherWorkspace, WorkspaceMember::ROLE_VIEWER);
        $membership = $this->membershipOf($otherWorkspace, $stranger);

        $this->actingAs($owner)
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertNotFound();
    }
}
