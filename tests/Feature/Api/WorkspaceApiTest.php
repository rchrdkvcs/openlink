<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_workspace_header_selects_the_active_workspace(): void
    {
        [$first, , $user] = $this->workspaceWithActiveDomain();
        $second = $this->workspaceFor($user, 'Second', 'second');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/workspaces/current')
            ->assertOk()
            ->assertJsonPath('data.id', $first->id);

        $this->getJson('/api/v1/workspaces/current', ['X-Workspace-Id' => (string) $second->id])
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.role', WorkspaceMember::ROLE_OWNER);
    }

    public function test_workspace_header_rejects_foreign_workspaces(): void
    {
        [, , $user] = $this->workspaceWithActiveDomain();
        [$otherWorkspace] = $this->workspaceWithActiveDomain('other.example.test');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/workspaces/current', ['X-Workspace-Id' => (string) $otherWorkspace->id])
            ->assertForbidden();

        $this->getJson('/api/v1/links', ['X-Workspace-Id' => (string) $otherWorkspace->id])
            ->assertForbidden();
    }

    public function test_workspaces_can_be_created_and_listed_via_api(): void
    {
        [, , $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', ['name' => 'Marketing'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Marketing');

        $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_domains_folders_tags_and_members_can_be_managed_via_api(): void
    {
        [, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/domains')
            ->assertOk()
            ->assertJsonPath('data.0.hostname', $domain->hostname);

        $this->postJson('/api/v1/domains', ['hostname' => 'https://links.example.test/'])
            ->assertCreated()
            ->assertJsonPath('data.hostname', 'links.example.test')
            ->assertJsonPath('data.status', Domain::STATUS_PENDING);

        $this->postJson('/api/v1/folders', ['name' => 'Campaigns'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Campaigns');

        $this->postJson('/api/v1/tags', ['name' => 'q3'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'q3');

        $this->getJson('/api/v1/tags')->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/members')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $user->id);

        $this->getJson('/api/v1/analytics?range=7d')
            ->assertOk()
            ->assertJsonPath('data.range.preset', '7d')
            ->assertJsonStructure(['data' => ['range', 'summary', 'timeseries', 'breakdowns', 'outcomes', 'top_links', 'top_qr_codes']]);
    }

    public function test_invite_link_lets_an_existing_user_join_the_workspace(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $existing = User::factory()->create();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/invite-links', [
            'role' => WorkspaceMember::ROLE_EDITOR,
        ])->assertCreated();

        $token = $response->json('data.invite_link.token');

        $this->getJson('/api/v1/invite-links')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($existing);

        $this->postJson("/api/v1/invite-links/{$token}/join")->assertOk();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $existing->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
    }
}
