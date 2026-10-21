<?php

namespace Tests\Feature\AppAssociations;

use App\Models\AppAssociation;
use App\Models\Domain;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class AppAssociationDomainLifecycleTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_transferring_a_domain_removes_its_app_associations(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $association = $this->association($domain);
        $target = $this->workspaceFor($owner, 'Other', 'other', WorkspaceMember::ROLE_ADMIN);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('domains.transfer', $domain), ['workspace_id' => $target->id])
            ->assertRedirect();

        $this->assertSame($target->id, $domain->refresh()->workspace_id);
        $this->assertModelMissing($association);
        $this->get('https://go.shop.test/.well-known/apple-app-site-association')->assertNotFound();
    }

    public function test_deleting_a_domain_removes_its_app_associations(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $association = $this->association($domain);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('domains.destroy', $domain))
            ->assertRedirect();

        $this->assertModelMissing($association);
    }

    public function test_deleting_a_workspace_removes_its_app_associations(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $association = $this->association($domain);

        $workspace->delete();

        $this->assertModelMissing($association);
        $this->assertNull(Domain::query()->find($domain->id));
    }

    public function test_disabling_a_domain_stops_publishing_its_app_associations(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->association($domain);
        $this->get('https://go.shop.test/.well-known/apple-app-site-association')->assertOk();

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('domains.disable', $domain))
            ->assertRedirect();

        $this->get('https://go.shop.test/.well-known/apple-app-site-association')->assertNotFound();
    }

    private function association(Domain $domain): AppAssociation
    {
        return AppAssociation::create([
            'domain_id' => $domain->id,
            'platform' => AppAssociation::PLATFORM_IOS,
            'team_id' => 'ABCDE12345',
            'identifier' => 'com.shop.app',
            'paths' => ['/*'],
        ]);
    }
}
