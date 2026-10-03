<?php

namespace Tests\Feature\Workspaces;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ActiveWorkspaceScopingTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_api_routes_refuse_records_outside_the_selected_workspace(): void
    {
        [$selected, , $user] = $this->workspaceWithActiveDomain('selected.example.test');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $user->id, 'role' => WorkspaceMember::ROLE_ADMIN]);
        $link = $this->shortLink($other, $otherDomain, 'outside');
        $folder = Folder::create(['workspace_id' => $other->id, 'name' => 'Outside']);

        Sanctum::actingAs($user);
        $headers = ['X-Workspace-Id' => (string) $selected->id];

        $this->patchJson("/api/v1/links/{$link->id}", ['destination_url' => 'https://example.com/changed'], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/domains/{$otherDomain->id}", [], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/folders/{$folder->id}", [], $headers)->assertForbidden();

        $this->assertSame('https://example.com/landing', $link->fresh()->destination_url);
        $this->assertNotNull(Domain::find($otherDomain->id));
        $this->assertNotNull(Folder::find($folder->id));
    }

    public function test_api_routes_accept_records_once_their_workspace_is_selected(): void
    {
        [, , $user] = $this->workspaceWithActiveDomain('selected.example.test');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $user->id, 'role' => WorkspaceMember::ROLE_ADMIN]);
        $link = $this->shortLink($other, $otherDomain, 'inside');

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/links/{$link->id}", ['X-Workspace-Id' => (string) $other->id])->assertOk();
    }

    public function test_web_routes_refuse_records_outside_the_session_workspace(): void
    {
        [$selected, , $user] = $this->workspaceWithActiveDomain('selected.example.test');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        WorkspaceMember::create(['workspace_id' => $other->id, 'user_id' => $user->id, 'role' => WorkspaceMember::ROLE_ADMIN]);
        $link = $this->shortLink($other, $otherDomain, 'outside');

        $this->actingInWorkspace($user, $selected)->delete("/short-links/{$link->id}")->assertForbidden();

        $this->assertNotNull(ShortLink::find($link->id));
    }
}
