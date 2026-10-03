<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ShortLinkApiTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_links_can_be_created_and_listed_via_api(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'destination_url' => 'https://example.com/target',
            'slug' => 'launch',
            'tags' => 'marketing, launch',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.slug', 'launch')
            ->assertJsonPath('data.short_url', 'https://'.$domain->hostname.'/launch')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('short_links', [
            'workspace_id' => $workspace->id,
            'slug' => 'launch',
        ]);

        $this->getJson('/api/v1/links')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'launch')
            ->assertJsonPath('data.0.tags.0.name', 'marketing');
    }

    public function test_link_creation_falls_back_to_default_domain(): void
    {
        [, , $user] = $this->workspaceWithActiveDomain('go.example.test');
        $default = Domain::create([
            'workspace_id' => null,
            'hostname' => 'localhost',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'default-token',
            'verified_at' => now(),
            'is_default' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/links', [
            'destination_url' => 'https://example.com/fallback-domain',
        ])->assertCreated()->assertJsonPath('data.domain.id', $default->id);
    }

    public function test_link_can_be_updated_archived_and_deleted(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $link = $this->shortLink($workspace, $domain, 'to-edit', [
            'destination_url' => 'https://example.com/original',
            'is_enabled' => true,
        ]);

        $this->patchJson('/api/v1/links/'.$link->id, [
            'destination_url' => 'https://example.com/updated',
            'is_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.destination_url', 'https://example.com/updated')
            ->assertJsonPath('data.status', 'disabled');

        $this->postJson('/api/v1/links/'.$link->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->deleteJson('/api/v1/links/'.$link->id)->assertOk();
        $this->assertDatabaseMissing('short_links', ['id' => $link->id]);
    }

    public function test_viewer_cannot_create_links_via_api(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();

        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER));

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'destination_url' => 'https://example.com/forbidden',
        ])->assertForbidden();
    }
}
