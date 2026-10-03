<?php

namespace Tests\Feature;

use App\Actions\InviteLinks\CreateInviteLink;
use App\Actions\Members\UpdateMemberRole;
use App\Enums\WorkspaceRole;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_changes_qr_codes_of_folder_links_without_managing_folders(): void
    {
        [$workspace, $domain] = $this->workspace('events');
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $link = $this->link($workspace, $domain, 'filed', $folder);
        $qrCode = QrCode::create(['short_link_id' => $link->id, 'name' => 'Poster', 'token' => 'editor-qr']);
        $editor = $this->member($workspace, WorkspaceRole::Editor);

        $this->actingAs($editor)->withSession(['workspace_id' => $workspace->id])
            ->patch(route('qr-codes.update', $qrCode), ['name' => 'Renamed poster'])
            ->assertRedirect();

        $this->actingAs($editor)->withSession(['workspace_id' => $workspace->id])
            ->patch(route('folders.update', $folder), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'name' => 'Renamed poster']);
        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Campaigns']);
    }

    public function test_viewer_cannot_change_or_delete_a_qr_code_linked_to_a_folder_link(): void
    {
        [$workspace, $domain] = $this->workspace('events');
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $link = $this->link($workspace, $domain, 'filed', $folder);
        $qrCode = QrCode::create(['short_link_id' => $link->id, 'name' => 'Poster', 'token' => 'viewer-qr']);
        Sanctum::actingAs($this->member($workspace, WorkspaceRole::Viewer));

        $this->patchJson('/api/v1/qr-codes/'.$qrCode->token, ['name' => 'Hijacked'])->assertForbidden();
        $this->deleteJson('/api/v1/qr-codes/'.$qrCode->token)->assertForbidden();

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'name' => 'Poster']);
    }

    public function test_qr_code_follows_the_rule_of_its_linked_short_link(): void
    {
        [$workspace] = $this->workspace('events');
        [$otherWorkspace, $otherDomain] = $this->workspace('other', 'other.test');
        $foreignLink = $this->link($otherWorkspace, $otherDomain, 'foreign');
        $qrCode = QrCode::create([
            'workspace_id' => $workspace->id,
            'short_link_id' => $foreignLink->id,
            'name' => 'Mismatched',
            'token' => 'mismatched-qr',
        ]);
        Sanctum::actingAs($this->member($workspace, WorkspaceRole::Editor));

        $this->patchJson('/api/v1/qr-codes/'.$qrCode->token, ['name' => 'Hijacked'])->assertForbidden();
        $this->getJson('/api/v1/qr-codes/'.$qrCode->token.'/export/svg')->assertForbidden();
    }

    public function test_member_roles_cannot_be_raised_to_owner(): void
    {
        [$workspace, , $owner] = $this->workspace('events');
        $editor = $this->member($workspace, WorkspaceRole::Editor);
        $membership = WorkspaceMember::query()->where('user_id', $editor->id)->firstOrFail();

        $this->actingAs($owner)->withSession(['workspace_id' => $workspace->id])
            ->patch(route('members.update', $membership), ['role' => WorkspaceMember::ROLE_OWNER])
            ->assertSessionHasErrors('role');

        try {
            app(UpdateMemberRole::class)->handle($owner, $membership, WorkspaceRole::Owner);
            $this->fail('Owner must not be assignable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $this->assertSame(WorkspaceMember::ROLE_EDITOR, $membership->fresh()->role);
    }

    public function test_invite_links_cannot_grant_ownership(): void
    {
        [$workspace, , $owner] = $this->workspace('events');

        $this->expectException(ValidationException::class);

        try {
            app(CreateInviteLink::class)->handle($owner, $workspace, WorkspaceRole::Owner, null, null);
        } finally {
            $this->assertDatabaseCount('invite_links', 0);
        }
    }

    public function test_current_workspace_falls_back_to_the_oldest_without_writing_the_session(): void
    {
        [$workspace, , $owner] = $this->workspace('events');

        $this->actingAs($owner)
            ->get(route('links.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('currentWorkspace.id', $workspace->id));

        $this->assertNull(session('workspace_id'));
    }

    public function test_switching_to_a_workspace_without_membership_is_forbidden(): void
    {
        [, , $owner] = $this->workspace('events');
        [$foreign] = $this->workspace('foreign', 'foreign.test');

        $this->actingAs($owner)
            ->post(route('workspaces.switch', $foreign))
            ->assertForbidden();

        $this->assertNull(session('workspace_id'));
    }

    private function workspace(string $slug, string $hostname = 'localhost'): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => ucfirst($slug), 'slug' => $slug, 'settings' => []]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMember::ROLE_OWNER]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'token-'.$slug,
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $owner];
    }

    private function member(Workspace $workspace, WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role->value]);

        return $user;
    }

    private function link(Workspace $workspace, Domain $domain, string $slug, ?Folder $folder = null): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'folder_id' => $folder?->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/landing',
        ]);
    }
}
