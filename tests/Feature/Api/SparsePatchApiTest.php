<?php

namespace Tests\Feature\Api;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Jobs\DeliverWebhook;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWebhookEndpoints;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class SparsePatchApiTest extends TestCase
{
    use CreatesWebhookEndpoints;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_sparse_link_patch_preserves_configuration_and_sensitive_fields(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Launch']);
        $created = $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'slug' => 'sparse',
            'destination_url' => 'https://example.com/target',
            'folder_id' => $folder->id,
            'fallback_url' => 'https://example.com/fallback',
            'activates_at' => '2030-01-01T00:00:00Z',
            'expires_at' => '2031-01-01T00:00:00Z',
            'visit_limit' => 100,
            'password' => 'secret',
            'tags' => 'launch',
            'conversion_goal' => 'Register',
            'preview_title' => 'Launch',
            'routing_rules' => [['destination_url' => 'https://example.com/routed']],
        ])->assertCreated();
        $id = $created->json('data.id');
        $before = $this->getJson('/api/v1/links/'.$id)->assertOk()->json('data');
        $password = ShortLink::findOrFail($id)->password_hash;

        $response = $this->patchJson('/api/v1/links/'.$id, [
            'is_enabled' => false,
            'workspace_id' => 99999,
            'password_hash' => 'injected',
            'successful_visits' => 99999,
            'revision' => 99999,
        ], ['If-Match' => $created->headers->get('ETag')])->assertOk()
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.revision', 2);

        foreach (['destination_url', 'slug', 'domain', 'folder', 'fallback_url', 'activates_at', 'expires_at', 'visit_limit', 'tags', 'routing_rules', 'has_password', 'preview', 'conversion_goal', 'successful_visits'] as $field) {
            $this->assertSame($before[$field], $response->json('data.'.$field), $field);
        }
        $this->assertDatabaseHas('short_links', ['id' => $id, 'workspace_id' => $workspace->id, 'password_hash' => $password]);
        $this->assertDatabaseHas('short_link_revisions', ['short_link_id' => $id, 'number' => 2, 'channel' => 'api']);
        $response = $this->patchJson('/api/v1/links/'.$id, [
            'destination_url' => 'https://example.com/new',
            'is_enabled' => true,
        ])->assertOk();
        foreach (['folder', 'fallback_url', 'activates_at', 'expires_at', 'visit_limit'] as $field) {
            $this->assertSame($before[$field], $response->json('data.'.$field), $field);
        }
    }

    public function test_nullable_link_fields_can_be_explicitly_cleared(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Launch']);
        $link = app(ShortLinkMutation::class)->create($user, $workspace, [
            'domain_id' => $domain->id,
            'slug' => 'clear',
            'destination_url' => 'https://example.com/target',
            'password' => 'secret',
            'tags' => 'launch',
            'routing_rules' => [['destination_url' => 'https://example.com/routed']],
            'folder_id' => $folder->id,
            'fallback_url' => 'https://example.com/fallback',
            'activates_at' => now()->addDay(),
            'expires_at' => now()->addYear(),
            'visit_limit' => 10,
            'conversion_goal' => 'Register',
            'preview_title' => 'Launch',
        ]);
        $this->patchJson('/api/v1/links/'.$link->id, [
            'folder_id' => null,
            'fallback_url' => null,
            'activates_at' => null,
            'expires_at' => null,
            'visit_limit' => null,
            'password' => '',
            'tags' => '',
            'conversion_goal' => '',
            'preview_title' => '',
            'routing_rules' => [],
        ])->assertOk()->assertJsonPath('data.folder', null)
            ->assertJsonPath('data.fallback_url', null)
            ->assertJsonPath('data.activates_at', null)
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.visit_limit', null)
            ->assertJsonPath('data.conversion_goal', null)
            ->assertJsonPath('data.preview.title', null)
            ->assertJsonPath('data.tags', [])
            ->assertJsonPath('data.routing_rules', [])
            ->assertJsonPath('data.has_password', false);
    }

    public function test_invalid_sparse_link_patch_does_not_write_or_record_history(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        [$foreign, $foreignDomain] = $this->workspaceWithActiveDomain('foreign.test');
        $folder = Folder::create(['workspace_id' => $foreign->id, 'name' => 'Private']);
        $link = $this->shortLink($workspace, $domain, 'invalid');
        Sanctum::actingAs($user);

        foreach ([
            ['destination_url' => null], ['destination_url' => ''], ['destination_url' => 'javascript:alert(1)'],
            ['is_enabled' => null], ['is_enabled' => ''], ['slug' => null], ['slug' => ''],
            ['domain_id' => null], ['domain_id' => $foreignDomain->id], ['folder_id' => $folder->id],
            ['visit_limit' => 0], ['routing_rules' => [['destination_url' => $link->shortUrl()]]],
        ] as $data) {
            $this->patchJson('/api/v1/links/'.$link->id, $data)->assertUnprocessable();
        }
        $this->assertSame(0, $link->fresh()->revision);
        $this->assertSame(0, $link->revisions()->count());
    }

    public function test_sparse_link_patch_revalidates_retained_destination_and_routing_when_address_changes(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);
        $link = $this->shortLink($workspace, $domain, 'before', ['destination_url' => 'https://'.$domain->hostname.'/after']);
        $this->patchJson('/api/v1/links/'.$link->id, ['slug' => 'after'])
            ->assertUnprocessable()->assertJsonValidationErrors('destination_url');

        $this->patchJson('/api/v1/links/'.$link->id, [
            'destination_url' => 'https://example.com/safe',
            'routing_rules' => [['destination_url' => 'https://'.$domain->hostname.'/routed']],
        ])->assertOk();
        $revision = $link->fresh()->revision;
        $this->patchJson('/api/v1/links/'.$link->id, ['slug' => 'routed'])
            ->assertUnprocessable()->assertJsonValidationErrors('routing_rules.0.destination_url');
        $this->assertSame('before', $link->fresh()->slug);
        $this->assertSame($revision, $link->fresh()->revision);
    }

    public function test_sparse_link_patch_enforces_roles_workspace_and_revision_preconditions(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        [$foreign, $foreignDomain] = $this->workspaceWithActiveDomain('foreign.test');
        $foreignLink = $this->shortLink($foreign, $foreignDomain, 'private');
        $link = $this->shortLink($workspace, $domain, 'revisions');
        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER));
        $this->patchJson('/api/v1/links/'.$link->id, ['is_enabled' => false])->assertForbidden();
        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR));
        $this->patchJson('/api/v1/links/'.$foreignLink->id, ['is_enabled' => false])->assertForbidden();
        $this->patchJson('/api/v1/links/'.$link->id, ['is_enabled' => false], ['If-Match' => '"0"'])
            ->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson('/api/v1/links/'.$link->id, ['is_enabled' => true], ['If-Match' => '"0"'])->assertStatus(412);
        $this->patchJson('/api/v1/links/'.$link->id, ['is_enabled' => true], ['If-Match' => 'invalid'])->assertStatus(412);
        $this->patchJson('/api/v1/links/'.$link->id, ['is_enabled' => true, 'expected_revision' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        $this->assertFalse($link->fresh()->is_enabled);
        $this->assertSame(2, $link->revisions()->count());
        $this->deleteJson('/api/v1/links/'.$link->id)->assertOk();
    }

    public function test_if_match_stays_a_412_when_another_write_happens_after_route_binding(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'concurrent');
        Sanctum::actingAs($user);
        $mutations = app(ShortLinkMutation::class);
        $this->app->resolving(ShortLinkMutation::class, function () use ($mutations, $user, $link): void {
            $mutations->update($user, $link, ['visit_limit' => 99]);
        });
        $this->patchJson('/api/v1/links/'.$link->id, ['is_enabled' => false], ['If-Match' => '"0"'])
            ->assertStatus(412);
        $this->assertTrue($link->fresh()->is_enabled);
        $this->assertSame(99, $link->fresh()->visit_limit);
        $this->assertSame(2, $link->revisions()->count());
    }

    public function test_sparse_mutation_uses_current_locked_state_instead_of_a_stale_loaded_model(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'original');
        $stale = $link->fresh('domain');
        $mutations = app(ShortLinkMutation::class);
        $mutations->update($user, $link, ['slug' => 'renamed', 'destination_url' => 'https://example.com/new', 'visit_limit' => 50]);
        $updated = $mutations->update($user, $stale, ['is_enabled' => false]);
        $this->assertSame('renamed', $updated->slug);
        $this->assertSame('https://example.com/new', $updated->destination_url);
        $this->assertSame(50, $updated->visit_limit);
        $this->assertFalse($updated->is_enabled);
        $this->assertSame(3, $updated->revision);
    }

    public function test_sparse_address_patch_invalidates_cache_and_records_the_preserved_state_in_webhooks(): void
    {
        Queue::fake();
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $this->webhookEndpoint($workspace);
        $link = $this->shortLink($workspace, $domain, 'original', ['fallback_url' => 'https://example.com/fallback', 'visit_limit' => 10]);
        $cache = app(ShortUrlCache::class);
        $this->assertSame($link->id, $cache->shortLinkId($domain, 'original'));
        Cache::put($cache->key($domain, 'renamed'), 999, 600);
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/links/'.$link->id, ['slug' => 'renamed'])->assertOk();
        $this->assertFalse(Cache::has($cache->key($domain, 'original')));
        $this->assertFalse(Cache::has($cache->key($domain, 'renamed')));
        $delivery = WebhookDelivery::query()->sole();
        $event = json_decode($delivery->payload, true);
        $this->assertSame('short_link.updated', $event['type']);
        $this->assertSame('renamed', $event['data']['slug']);
        $this->assertSame('https://example.com/fallback', $event['data']['fallback_url']);
        $this->assertSame(10, $event['data']['visit_limit']);
        Queue::assertPushed(DeliverWebhook::class, 1);
        $this->patchJson('/api/v1/links/'.$link->id, ['destination_url' => 'https://localhost/renamed'])->assertUnprocessable();
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $this->assertSame(2, $link->revisions()->count());
    }

    public function test_workspace_patch_preserves_omissions_and_clears_explicit_nulls(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $workspace->update(['preferred_domain_id' => $domain->id, 'icon' => 'rocket', 'color' => 'blue']);
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['name' => 'Renamed'])
            ->assertOk()->assertJsonPath('data.preferred_domain_id', $domain->id)
            ->assertJsonPath('data.icon', 'rocket')->assertJsonPath('data.color', 'blue');
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['icon' => null, 'color' => null, 'preferred_domain_id' => null])
            ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.icon', null)
            ->assertJsonPath('data.color', null)->assertJsonPath('data.preferred_domain_id', null);
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['name' => null])->assertUnprocessable();
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['name' => ''])->assertUnprocessable();
    }

    public function test_workspace_patch_keeps_domain_and_role_authorization(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        [$foreign, $domain] = $this->workspaceWithActiveDomain('foreign.test');
        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['preferred_domain_id' => $domain->id])->assertUnprocessable();
        $this->patchJson('/api/v1/workspaces/'.$foreign->id, ['name' => 'Forbidden'])->assertForbidden();
        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR));
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['name' => 'Forbidden'])->assertForbidden();
        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN));
        $this->patchJson('/api/v1/workspaces/'.$workspace->id, ['color' => 'blue'])->assertOk();
    }

    public function test_instance_settings_patch_is_partial_and_preserves_false_and_clear_values(): void
    {
        $settings = app(InstanceSettings::class);
        $settings->set('dns_target', 'app.example.test');
        $settings->set('reserved_slugs', ['launch']);
        $settings->set('destination_monitoring', true);
        Sanctum::actingAs(User::factory()->create(['is_instance_admin' => true]));
        $before = $this->getJson('/api/v1/instance-settings')->assertOk()->json('data');
        $this->patchJson('/api/v1/instance-settings', ['slug_length' => 12])
            ->assertOk()->assertJsonPath('data.slug_length', 12);
        $after = $this->getJson('/api/v1/instance-settings')->assertOk()->json('data');
        unset($before['slug_length'], $after['slug_length']);
        $this->assertSame($before, $after);
        $this->assertDatabaseCount('domains', 0);
        $this->patchJson('/api/v1/instance-settings', ['destination_monitoring' => false, 'dns_target' => null, 'reserved_slugs' => ''])
            ->assertOk()->assertJsonPath('data.destination_monitoring', false)
            ->assertJsonPath('data.dns_target', '')->assertJsonPath('data.reserved_slugs', []);
        foreach (['slug_length' => null, 'default_domain' => '', 'registration_mode' => 'invalid', 'require_email_verification' => null] as $field => $value) {
            $this->patchJson('/api/v1/instance-settings', [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        Sanctum::actingAs(User::factory()->create(['is_instance_admin' => false]));
        $this->getJson('/api/v1/instance-settings')->assertForbidden();
        $this->patchJson('/api/v1/instance-settings', ['slug_length' => 15])->assertForbidden();
    }

    public function test_instance_admin_can_patch_without_email_verification_and_cannot_change_instance_name(): void
    {
        $settings = app(InstanceSettings::class);
        $settings->set('require_email_verification', true);
        Sanctum::actingAs(User::factory()->create(['is_instance_admin' => true, 'email_verified_at' => null]));
        $this->getJson('/api/v1/instance-settings')->assertOk();
        $this->patchJson('/api/v1/instance-settings', ['slug_length' => 10, 'instance_name' => 'Injected'])
            ->assertOk()->assertJsonPath('data.slug_length', 10)->assertJsonPath('data.instance_name', 'Openlink');
    }

    public function test_dashboard_forms_keep_their_required_fields_and_workspace_reset_behavior(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $user->forceFill(['is_instance_admin' => true])->save();
        $link = $this->shortLink($workspace, $domain, 'dashboard');
        $this->actingInWorkspace($user, $workspace);
        $this->patch(route('short-links.update', $link), ['tags' => 'launch'])
            ->assertSessionHasErrors(['destination_url', 'is_enabled']);
        $this->patch(route('workspaces.update', $workspace), ['color' => 'blue'])
            ->assertSessionHasErrors('name');
        $this->patch(route('instance-settings.update'), ['slug_length' => 10])
            ->assertSessionHasErrors(['registration_mode', 'default_domain']);
        $workspace->update(['preferred_domain_id' => $domain->id, 'icon' => 'rocket', 'color' => 'blue']);
        $this->patch(route('workspaces.update', $workspace), ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertNull($workspace->fresh()->preferred_domain_id);
        $this->assertNull($workspace->fresh()->icon);
        $this->assertNull($workspace->fresh()->color);
    }

    public function test_folder_filter_is_applied_and_never_leaks_other_workspace_links(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        [$foreign, $foreignDomain] = $this->workspaceWithActiveDomain('foreign.test');
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Launch']);
        $foreignFolder = Folder::create(['workspace_id' => $foreign->id, 'name' => 'Private']);
        $filed = $this->shortLink($workspace, $domain, 'filed', ['folder_id' => $folder->id]);
        $unfiled = $this->shortLink($workspace, $domain, 'unfiled');
        $this->shortLink($foreign, $foreignDomain, 'private', ['folder_id' => $foreignFolder->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/links?folder='.$folder->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $filed->id);
        $this->getJson('/api/v1/links?folder=unfiled')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unfiled->id);
        $this->getJson('/api/v1/links?folder='.$foreignFolder->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/links?folder=invalid')->assertUnprocessable()->assertJsonValidationErrors('folder');
        $this->getJson('/api/v1/links?folder='.$foreignFolder->id, ['X-Workspace-Id' => (string) $foreign->id])->assertForbidden();
    }
}
