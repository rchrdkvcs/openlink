<?php

namespace Tests\Feature\Members;

use App\Models\Folder;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class MembershipLifecycleTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_removing_a_member_keeps_workspace_folders(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $membership = $this->membershipOf($workspace, $editor);

        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);

        $this->actingAs($owner)
            ->delete(route('members.destroy', $membership))
            ->assertRedirect();

        $this->assertDatabaseMissing('workspace_members', ['id' => $membership->id]);
        $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    }

    public function test_admin_cannot_remove_themselves(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);
        $membership = $this->membershipOf($workspace, $admin);

        $this->actingInWorkspace($admin, $workspace)
            ->delete(route('members.destroy', $membership))
            ->assertForbidden();
    }

    public function test_member_can_leave_a_workspace(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);

        $this->actingInWorkspace($editor, $workspace)
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

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('members.leave'))
            ->assertForbidden();
    }

    public function test_owner_can_transfer_ownership(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);
        $membership = $this->membershipOf($workspace, $admin);

        $this->actingAs($owner)
            ->post(route('members.transfer-ownership', $membership))
            ->assertRedirect();

        $this->assertSame(WorkspaceMember::ROLE_OWNER, $membership->fresh()->role);
        $this->assertSame($admin->id, $workspace->fresh()->owner_id);
        $this->assertSame(
            WorkspaceMember::ROLE_ADMIN,
            $this->membershipOf($workspace, $owner)->role,
        );
    }

    public function test_admin_cannot_transfer_ownership(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $membership = $this->membershipOf($workspace, $editor);

        $this->actingInWorkspace($admin, $workspace)
            ->post(route('members.transfer-ownership', $membership))
            ->assertForbidden();
    }
}
