<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\InviteLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InviteLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_revoke_an_invite_link(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();

        $this->actingAs($owner)->post(route('invite-links.store'), [
            'role' => WorkspaceMember::ROLE_VIEWER,
            'expires_in_days' => 7,
            'max_uses' => 5,
        ])->assertRedirect();

        $link = InviteLink::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame(WorkspaceMember::ROLE_VIEWER, $link->role);
        $this->assertSame(5, $link->max_uses);
        $this->assertNotNull($link->expires_at);
        $this->assertTrue($link->isUsable());

        $this->actingAs($owner)->delete(route('invite-links.destroy', $link))->assertRedirect();

        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertFalse($link->fresh()->isUsable());
    }

    public function test_viewer_cannot_create_an_invite_link(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $viewer = $this->member($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAs($viewer)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('invite-links.store'), ['role' => WorkspaceMember::ROLE_EDITOR])
            ->assertForbidden();
    }

    public function test_owner_role_cannot_be_carried_by_an_invite_link(): void
    {
        [, $owner] = $this->workspaceWithOwner();

        $this->actingAs($owner)
            ->post(route('invite-links.store'), ['role' => WorkspaceMember::ROLE_OWNER])
            ->assertSessionHasErrors('role');
    }

    public function test_authenticated_user_joins_via_invite_link(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $link = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('join.store', $link))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertSame(1, $link->fresh()->uses);
    }

    public function test_joining_again_keeps_existing_role_and_does_not_consume_a_use(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $link = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_VIEWER);
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('join.store', $link))->assertRedirect();

        $this->assertSame(0, $link->fresh()->uses);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => WorkspaceMember::ROLE_ADMIN,
        ]);
    }

    public function test_expired_revoked_or_exhausted_links_cannot_be_used(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $user = User::factory()->create();

        $expired = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR, ['expires_at' => now()->subDay()]);
        $revoked = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR, ['revoked_at' => now()]);
        $exhausted = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR, ['max_uses' => 1, 'uses' => 1]);

        foreach ([$expired, $revoked, $exhausted] as $link) {
            $this->actingAs($user)->post(route('join.store', $link))->assertGone();
        }

        $this->assertDatabaseMissing('workspace_members', ['user_id' => $user->id]);
    }

    public function test_join_page_renders_for_guests_and_for_unusable_links(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $link = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);

        $this->get(route('join.show', $link))->assertOk();

        $link->update(['revoked_at' => now()]);
        $this->get(route('join.show', $link))->assertOk();

        $this->get('/join/not-a-real-token')->assertNotFound();
    }

    public function test_invite_link_does_not_allow_registration_when_instance_is_closed(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $link = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);
        app(InstanceSettings::class)->set('registration_mode', 'closed');

        $this->post('/register', [
            'name' => 'Blocked',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_token' => $link->token,
        ])->assertForbidden();

        // Existing users can still join through the link.
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('join.store', $link))->assertRedirect();
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    }

    /** @return array{Workspace, User} */
    private function workspaceWithOwner(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => 'Events',
            'slug' => 'events',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        Domain::create([
            'workspace_id' => null,
            'hostname' => 'localhost',
            'status' => Domain::STATUS_VERIFIED,
            'verification_token' => Str::random(12),
            'is_default' => true,
            'verified_at' => now(),
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

    /** @param array<string, mixed> $attributes */
    private function inviteLink(Workspace $workspace, User $creator, string $role, array $attributes = []): InviteLink
    {
        return InviteLink::create([
            'workspace_id' => $workspace->id,
            'created_by_id' => $creator->id,
            'role' => $role,
            'token' => Str::random(48),
            ...$attributes,
        ]);
    }
}
