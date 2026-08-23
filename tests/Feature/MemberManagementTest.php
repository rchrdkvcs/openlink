<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_a_member_role(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);
        $viewer = $this->member($workspace, WorkspaceMember::ROLE_VIEWER);
        $membership = $this->membership($workspace, $viewer);

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertRedirect();

        $this->assertSame(WorkspaceMember::ROLE_EDITOR, $membership->fresh()->role);
    }

    public function test_admin_members_page_exposes_member_management_controls(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $workspace->id])
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageMembers', true)
                ->has('members', 2));
    }

    public function test_owner_role_cannot_be_changed_or_removed(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);
        $ownerMembership = $this->membership($workspace, $owner);

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('members.update', $ownerMembership), ['role' => WorkspaceMember::ROLE_VIEWER])
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('members.destroy', $ownerMembership))
            ->assertForbidden();
    }

    public function test_editor_cannot_manage_members(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $editor = $this->member($workspace, WorkspaceMember::ROLE_EDITOR);
        $viewer = $this->member($workspace, WorkspaceMember::ROLE_VIEWER);
        $membership = $this->membership($workspace, $viewer);

        $this->actingAs($editor)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertForbidden();
    }

    public function test_removing_a_member_purges_their_folder_permissions(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $editor = $this->member($workspace, WorkspaceMember::ROLE_EDITOR);
        $membership = $this->membership($workspace, $editor);

        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        FolderPermission::create([
            'folder_id' => $folder->id,
            'user_id' => $editor->id,
            'permission' => FolderPermission::CAN_EDIT,
        ]);

        $this->actingAs($owner)
            ->delete(route('members.destroy', $membership))
            ->assertRedirect();

        $this->assertDatabaseMissing('workspace_members', ['id' => $membership->id]);
        $this->assertDatabaseMissing('folder_permissions', ['user_id' => $editor->id, 'folder_id' => $folder->id]);
    }

    public function test_admin_cannot_remove_themselves(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);
        $membership = $this->membership($workspace, $admin);

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('members.destroy', $membership))
            ->assertForbidden();
    }

    public function test_member_can_leave_a_workspace(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $editor = $this->member($workspace, WorkspaceMember::ROLE_EDITOR);

        $this->actingAs($editor)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('members.leave'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseMissing('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $editor->id,
        ]);
    }

    public function test_owner_cannot_leave_their_workspace(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();

        $this->actingAs($owner)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('members.leave'))
            ->assertForbidden();
    }

    public function test_owner_can_transfer_ownership(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);
        $membership = $this->membership($workspace, $admin);

        $this->actingAs($owner)
            ->post(route('members.transfer-ownership', $membership))
            ->assertRedirect();

        $this->assertSame(WorkspaceMember::ROLE_OWNER, $membership->fresh()->role);
        $this->assertSame($admin->id, $workspace->fresh()->owner_id);
        $this->assertSame(
            WorkspaceMember::ROLE_ADMIN,
            $this->membership($workspace, $owner)->role,
        );
    }

    public function test_admin_cannot_transfer_ownership(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);
        $editor = $this->member($workspace, WorkspaceMember::ROLE_EDITOR);
        $membership = $this->membership($workspace, $editor);

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('members.transfer-ownership', $membership))
            ->assertForbidden();
    }

    public function test_members_of_another_workspace_are_out_of_reach(): void
    {
        [, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace] = $this->workspaceWithOwner('other');
        $stranger = $this->member($otherWorkspace, WorkspaceMember::ROLE_VIEWER);
        $membership = $this->membership($otherWorkspace, $stranger);

        $this->actingAs($owner)
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertNotFound();
    }

    /** @return array{Workspace, User} */
    private function workspaceWithOwner(string $slug = 'events'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        return [$workspace, $owner];
    }

    private function member(Workspace $workspace, string $role): User
    {
        $user = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    private function membership(Workspace $workspace, User $user): WorkspaceMember
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }
}
