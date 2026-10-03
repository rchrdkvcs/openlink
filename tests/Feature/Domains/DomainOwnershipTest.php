<?php

namespace Tests\Feature\Domains;

use App\Models\Domain;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class DomainOwnershipTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_workspace_manager_can_delete_workspace_domain(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();

        $this->actingInWorkspace($user, $workspace)
            ->delete(route('domains.destroy', $domain))
            ->assertRedirect();

        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_default_domain_cannot_be_deleted_from_workspace_domains(): void
    {
        [$workspace, , $user] = $this->workspaceWithActiveDomain();
        $defaultDomain = Domain::create([
            'workspace_id' => null,
            'hostname' => 'short.test',
            'status' => Domain::STATUS_ACTIVE,
            'is_default' => true,
            'verification_token' => 'default-token',
            'verified_at' => now(),
        ]);

        $this->actingInWorkspace($user, $workspace)
            ->delete(route('domains.destroy', $defaultDomain))
            ->assertForbidden();

        $this->assertDatabaseHas('domains', ['id' => $defaultDomain->id]);
    }

    public function test_domain_can_transfer_to_another_managed_workspace_when_unused(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $otherWorkspace = $this->workspaceFor($user, 'Other', 'other', WorkspaceMember::ROLE_ADMIN);

        $this->actingInWorkspace($user, $workspace)
            ->post(route('domains.transfer', $domain), ['workspace_id' => $otherWorkspace->id])
            ->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'workspace_id' => $otherWorkspace->id,
        ]);
    }

    public function test_domain_transfer_is_blocked_when_domain_has_links(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $otherWorkspace = $this->workspaceFor($user, 'Other', 'other', WorkspaceMember::ROLE_ADMIN);
        $this->shortLink($workspace, $domain, 'kept', ['destination_url' => 'https://example.com/kept']);

        $this->actingInWorkspace($user, $workspace)
            ->post(route('domains.transfer', $domain), ['workspace_id' => $otherWorkspace->id])
            ->assertSessionHasErrors('workspace_id');

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'workspace_id' => $workspace->id,
        ]);
    }
}
