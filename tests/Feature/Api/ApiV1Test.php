<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_can_be_exchanged_for_a_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'browser-extension',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $token = $response->json('token');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'browser-extension',
        ])->assertUnprocessable();
    }

    public function test_token_issuance_requires_two_factor_code_when_enabled(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => 'secret-password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'browser-extension',
        ])->assertUnprocessable()->assertJsonValidationErrors('one_time_password');

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'browser-extension',
            'one_time_password' => $google2fa->getCurrentOtp($secret),
        ])->assertCreated();
    }

    public function test_current_token_can_be_revoked(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'browser-extension',
        ])->json('token');

        $this->deleteJson('/api/v1/auth/token', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/links')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_links_can_be_created_and_listed_via_api(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
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
        [, , $user] = $this->workspaceAndDomain('go.example.test');
        $default = Domain::create([
            'workspace_id' => null,
            'hostname' => 'localhost',
            'status' => Domain::STATUS_VERIFIED,
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
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        Sanctum::actingAs($user);

        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'to-edit',
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

    public function test_workspace_header_selects_the_active_workspace(): void
    {
        [$first, , $user] = $this->workspaceAndDomain();

        $second = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Second',
            'slug' => 'second',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $second->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

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
        [, , $user] = $this->workspaceAndDomain();
        [$otherWorkspace] = $this->workspaceAndDomain('other.example.test');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/workspaces/current', ['X-Workspace-Id' => (string) $otherWorkspace->id])
            ->assertForbidden();

        $this->getJson('/api/v1/links', ['X-Workspace-Id' => (string) $otherWorkspace->id])
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_links_via_api(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();

        $viewer = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);

        Sanctum::actingAs($viewer);

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'destination_url' => 'https://example.com/forbidden',
        ])->assertForbidden();
    }

    public function test_editor_cannot_create_link_in_folder_without_permission(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();

        $editor = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $editor->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);

        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Locked']);

        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'folder_id' => $folder->id,
            'destination_url' => 'https://example.com/locked',
        ])->assertForbidden();
    }

    public function test_domains_folders_tags_and_members_can_be_managed_via_api(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
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

    public function test_inviting_an_existing_user_adds_them_to_the_workspace(): void
    {
        [$workspace, , $owner] = $this->workspaceAndDomain();
        $existing = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/invitations', [
            'email' => $existing->email,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ])->assertCreated();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $existing->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
    }

    public function test_qr_code_can_be_created_and_previewed_via_api(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        Sanctum::actingAs($user);

        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'qr-target',
            'destination_url' => 'https://example.com/qr',
            'is_enabled' => true,
        ]);

        $create = $this->postJson('/api/v1/links/'.$link->id.'/qr-codes', ['name' => 'Poster']);
        $create->assertCreated()->assertJsonStructure(['data' => ['id', 'name', 'token', 'public_url']]);

        $token = $create->json('data.token');

        $this->get('/api/v1/qr-codes/'.$token.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->get('/api/v1/qr-codes/'.$token.'/export/svg')
            ->assertOk()
            ->assertDownload($token.'.svg');
    }

    public function test_workspaces_can_be_created_and_listed_via_api(): void
    {
        [, , $user] = $this->workspaceAndDomain();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', ['name' => 'Marketing'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Marketing');

        $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_instance_settings_require_instance_admin(): void
    {
        [, , $user] = $this->workspaceAndDomain();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/instance-settings')->assertForbidden();

        $user->forceFill(['is_instance_admin' => true])->save();

        $this->getJson('/api/v1/instance-settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['registration_mode', 'default_domain', 'slug_length']]);
    }

    public function test_profile_can_be_read_and_updated_via_api(): void
    {
        [$workspace, , $user] = $this->workspaceAndDomain();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('workspaces.0.id', $workspace->id)
            ->assertJsonPath('workspaces.0.role', WorkspaceMember::ROLE_OWNER);

        $this->patchJson('/api/v1/me', [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('user.name', 'Renamed');
    }

    /** @return array{Workspace, Domain, User} */
    private function workspaceAndDomain(string $hostname = 'localhost'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Events '.str()->random(4),
            'slug' => 'events-'.strtolower(str()->random(6)),
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_VERIFIED,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $user];
    }
}
