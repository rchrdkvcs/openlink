<?php

namespace Tests\Feature\ShortLinks;

use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use App\Models\Tag;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class LinkHistoryTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_an_api_update_records_one_revision_with_actor_channel_and_diff(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/b',
            'is_enabled' => true,
            'tags' => 'spring',
            'routing_rules' => [$this->franceRule()],
        ])->assertOk()->assertJsonPath('data.revision', 2);

        $this->getJson("/api/v1/links/$id/revisions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_revision', 2)
            ->assertJsonPath('data.0.number', 2)
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.channel', 'api')
            ->assertJsonPath('data.0.actor.id', $owner->id)
            ->assertJsonPath('data.0.changes.0.field', 'destination_url')
            ->assertJsonPath('data.0.changes.0.before', 'https://example.com/a')
            ->assertJsonPath('data.0.changes.0.after', 'https://example.com/b')
            ->assertJsonPath('data.0.changes.1.field', 'tags')
            ->assertJsonPath('data.0.changes.2.field', 'routing_rules')
            ->assertJsonPath('data.1.number', 1)
            ->assertJsonPath('data.1.action', 'created');
    }

    public function test_dashboard_moves_and_archives_each_record_one_revision_and_no_op_edits_record_none(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring']);
        $this->actingInWorkspace($owner, $workspace);

        $this->post('/short-links', ['domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/a'])->assertRedirect();
        $link = ShortLink::query()->firstOrFail();

        $this->patch("/short-links/$link->id", ['destination_url' => 'https://example.com/a', 'is_enabled' => true])->assertRedirect();
        $this->post("/short-links/$link->id/move", ['folder_id' => $folder->id])->assertRedirect();
        $this->post("/short-links/$link->id/archive")->assertRedirect();
        $this->post("/short-links/$link->id/archive")->assertRedirect();

        $this->getJson("/short-links/$link->id/revisions")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action', 'archived')
            ->assertJsonPath('data.0.channel', 'dashboard')
            ->assertJsonPath('data.0.changes.0.field', 'is_archived')
            ->assertJsonPath('data.1.action', 'moved')
            ->assertJsonPath('data.1.changes.0.after.name', 'Spring')
            ->assertJsonPath('data.2.action', 'created');
    }

    public function test_a_rolled_back_mutation_leaves_no_revision(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/changed',
            'is_enabled' => true,
            'routing_rules' => [['name' => 'Loop', 'conditions' => [], 'destination_url' => 'https://localhost/launch']],
        ])->assertUnprocessable()->assertJsonValidationErrors('routing_rules.0.destination_url');

        $this->assertSame('https://example.com/a', $this->link($id)->destination_url);
        $this->assertSame(1, $this->link($id)->revision);
        $this->assertSame(1, ShortLinkRevision::query()->count());
    }

    public function test_passwords_never_appear_in_snapshots_or_diffs(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['password' => 'very-secret-word']);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/a',
            'is_enabled' => true,
            'password' => null,
        ])->assertOk();

        $hash = $this->link($id)->getRawOriginal('password_hash');
        $stored = ShortLinkRevision::query()->get()->toJson();
        $this->assertStringNotContainsString('very-secret-word', $stored);
        $this->assertStringNotContainsString('password_hash', $stored);
        $this->assertNull($hash);

        $this->getJson("/api/v1/links/$id/revisions/2")
            ->assertOk()
            ->assertJsonPath('data.changes.0.field', 'has_password')
            ->assertJsonPath('data.changes.0.before', true)
            ->assertJsonPath('data.changes.0.after', false)
            ->assertJsonMissingPath('data.snapshot.password');
    }

    public function test_viewers_read_history_but_cannot_restore(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);
        $this->updateDestination($id, 'https://example.com/b', 1);

        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER));

        $this->getJson("/api/v1/links/$id/revisions")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/links/$id/revisions/1")->assertOk()->assertJsonPath('data.restore.restorable', true);
        $this->restore($id, 1, 2)->assertForbidden();
        $this->assertSame('https://example.com/b', $this->link($id)->destination_url);
    }

    public function test_history_of_another_workspace_is_forbidden(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);

        [, $stranger] = $this->workspaceWithOwner('Other');
        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/links/$id/revisions")->assertForbidden();
        $this->getJson("/api/v1/links/$id/revisions/1")->assertForbidden();
        $this->restore($id, 1, 1)->assertForbidden();
    }

    public function test_restore_copies_the_configuration_forward_as_a_new_revision(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring']);
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, [
            'folder_id' => $folder->id,
            'tags' => 'spring, launch',
            'visit_limit' => 50,
            'routing_rules' => [$this->franceRule()],
        ]);
        $ruleId = $this->link($id)->routingRules()->value('id');
        $qrCode = $this->link($id)->qrCodes()->create(['workspace_id' => $workspace->id, 'name' => 'Poster', 'token' => 'poster-token']);
        $this->link($id)->forceFill(['successful_visits' => 7])->save();
        $event = $this->analyticsEvent($this->link($id), ['routing_rule_id' => $ruleId]);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/wrong',
            'is_enabled' => false,
            'folder_id' => null,
            'tags' => '',
            'visit_limit' => null,
            'routing_rules' => [],
            'expected_revision' => 1,
        ])->assertOk();

        $this->assertNull($this->withHeaders(['Host' => 'localhost'])->get('/launch')->headers->get('Location'));

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertOk()
            ->assertJsonPath('data.restore.restorable', true)
            ->assertJsonPath('data.restore.changes.0.field', 'destination_url')
            ->assertJsonPath('data.restore.changes.0.before', 'https://example.com/wrong')
            ->assertJsonPath('data.restore.changes.0.after', 'https://example.com/a');

        $this->restore($id, 1, 2)
            ->assertOk()
            ->assertJsonPath('data.destination_url', 'https://example.com/a')
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.folder.id', $folder->id)
            ->assertJsonPath('data.visit_limit', 50)
            ->assertJsonPath('data.slug', 'launch')
            ->assertJsonPath('data.successful_visits', 7)
            ->assertJsonPath('data.revision', 3)
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.routing_rules.0.name', 'France');

        $this->getJson("/api/v1/links/$id/revisions")
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action', 'restored')
            ->assertJsonPath('data.0.restored_from', 1)
            ->assertJsonPath('data.1.action', 'updated');

        $this->assertSame('poster-token', $qrCode->fresh()->token);
        $this->assertDatabaseHas('analytics_events', ['id' => $event->id, 'short_link_id' => $id]);
        $this->withHeaders(['Host' => 'localhost'])->get('/launch')->assertRedirect('https://example.com/a');
        $this->withHeaders(['Host' => 'localhost'])->get('/qr/poster-token')->assertRedirect('https://example.com/a');
    }

    public function test_restore_keeps_existing_routing_rule_identity_and_recreates_deleted_rules(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['routing_rules' => [
            $this->franceRule(),
            ['name' => 'Other', 'conditions' => [], 'destination_url' => 'https://example.com/other'],
        ]]);
        [$franceId, $otherId] = $this->link($id)->routingRules()->pluck('id')->all();

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/a',
            'is_enabled' => true,
            'routing_rules' => [['id' => $franceId, ...$this->franceRule(), 'destination_url' => 'https://example.com/fr-new']],
        ])->assertOk();

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.notes.0', 'Routing rules deleted since this revision come back as new rules: Other.');

        $this->restore($id, 1, 2)->assertOk();

        $rules = $this->link($id)->routingRules()->get();
        $this->assertSame($franceId, $rules[0]->id);
        $this->assertSame('https://example.com/fr', $rules[0]->destination_url);
        $this->assertNotSame($otherId, $rules[1]->id);
        $this->assertSame('Other', $rules[1]->name);
    }

    public function test_a_stale_restore_or_edit_cannot_overwrite_a_newer_change(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);
        $this->updateDestination($id, 'https://example.com/b', 1);
        $this->updateDestination($id, 'https://example.com/c', 2);

        $this->restore($id, 1, 2)->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/d',
            'is_enabled' => true,
            'expected_revision' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('expected_revision');
        $this->postJson("/api/v1/links/$id/revisions/1/restore")->assertUnprocessable()->assertJsonValidationErrors('expected_revision');

        $this->assertSame('https://example.com/c', $this->link($id)->destination_url);
        $this->assertSame(3, ShortLinkRevision::query()->count());
    }

    public function test_restore_revalidates_destination_safety_against_the_current_address(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['destination_url' => 'https://localhost/renamed']);

        $this->patchJson("/api/v1/links/$id", [
            'slug' => 'renamed',
            'destination_url' => 'https://example.com/safe',
            'is_enabled' => true,
        ])->assertOk();

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.notes.0', 'The short URL stays https://localhost/renamed. Restoring never changes the address.');
        $this->restore($id, 1, 2)->assertUnprocessable()->assertJsonValidationErrors('destination_url');
        $this->assertSame('https://example.com/safe', $this->link($id)->destination_url);
        $this->assertSame('renamed', $this->link($id)->slug);
    }

    public function test_restore_revalidates_the_routing_schema(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['routing_rules' => [$this->franceRule()]]);
        $this->patchJson("/api/v1/links/$id", ['destination_url' => 'https://example.com/a', 'is_enabled' => true, 'routing_rules' => []])->assertOk();

        $revision = ShortLinkRevision::query()->where('number', 1)->firstOrFail();
        $snapshot = $revision->snapshot;
        $snapshot['routing_rules'][0]['conditions'][0]['type'] = 'planet';
        $revision->update(['snapshot' => $snapshot]);

        $this->restore($id, 1, 2)->assertUnprocessable()->assertJsonValidationErrors('routing_rules.0.conditions.0.type');
        $this->assertSame(0, $this->link($id)->routingRules()->count());
    }

    public function test_restore_rechecks_the_members_current_role(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        Sanctum::actingAs($editor);
        $id = $this->createLink($domain->id);
        $this->updateDestination($id, 'https://example.com/b', 1);

        $this->membershipOf($workspace, $editor)->update(['role' => WorkspaceMember::ROLE_VIEWER]);

        $this->restore($id, 1, 2)->assertForbidden();
    }

    public function test_deleted_folders_and_tags_have_explicit_restore_behavior(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring']);
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['folder_id' => $folder->id, 'tags' => 'spring']);
        $this->patchJson("/api/v1/links/$id", ['destination_url' => 'https://example.com/b', 'is_enabled' => true, 'tags' => ''])->assertOk();
        $folder->delete();
        Tag::query()->where('name', 'spring')->delete();

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.notes', [
                'The folder “Spring” no longer exists. The link will be left without a folder.',
                'These tags were deleted and will be created again: spring.',
            ]);

        $this->restore($id, 1, 2)
            ->assertOk()
            ->assertJsonPath('data.folder', null)
            ->assertJsonPath('data.tags.0.name', 'spring');
    }

    public function test_deleted_actors_stay_in_history_without_a_name(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        Sanctum::actingAs($editor);
        $id = $this->createLink($domain->id);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $editor->delete();

        Sanctum::actingAs($viewer);
        $this->getJson("/api/v1/links/$id/revisions")
            ->assertOk()
            ->assertJsonPath('data.0.actor', null)
            ->assertJsonPath('data.0.channel', 'api');
    }

    public function test_unsupported_schema_revisions_explain_why_they_cannot_be_restored(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);
        $this->updateDestination($id, 'https://example.com/b', 1);
        ShortLinkRevision::query()->where('number', 1)->update(['schema_version' => 99]);
        $message = "Revision 1 was recorded in a format this version of Openlink can't restore. Copy its settings from the history and edit the link instead.";

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertOk()
            ->assertJsonPath('data.restore.restorable', false)
            ->assertJsonPath('data.restore.reason', $message);
        $this->restore($id, 1, 2)->assertUnprocessable()->assertJsonPath('errors.revision.0', $message);
    }

    public function test_the_current_revision_is_not_restorable_and_unknown_revisions_are_missing(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.restorable', false)
            ->assertJsonPath('data.restore.reason', 'This is the current revision.');
        $this->restore($id, 1, 1)->assertUnprocessable()->assertJsonPath('errors.revision.0', 'This is the current revision.');
        $this->getJson("/api/v1/links/$id/revisions/9")->assertNotFound();
        $this->restore($id, 9, 1)->assertNotFound();
    }

    public function test_restoring_a_configuration_identical_to_the_current_one_records_nothing(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);
        $this->updateDestination($id, 'https://example.com/b', 1);
        $this->updateDestination($id, 'https://example.com/a', 2);

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.restorable', false)
            ->assertJsonPath('data.restore.reason', 'This revision matches the current configuration.');
        $this->restore($id, 1, 3)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame(3, ShortLinkRevision::query()->count());
    }

    public function test_restore_keeps_routing_rules_and_variants_that_have_analytics_turned_off(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $split = ['name' => 'Split', 'type' => 'split_test', 'conditions' => [], 'variants' => [
            ['name' => 'A', 'destination_url' => 'https://example.com/va', 'weight' => 50],
            ['name' => 'Z', 'destination_url' => 'https://example.com/vz', 'weight' => 50],
        ]];
        $id = $this->createLink($domain->id, ['routing_rules' => [$split]]);
        $splitRule = $this->link($id)->routingRules()->with('variants')->firstOrFail();
        $variantA = $splitRule->variants[0]->id;
        $variantZ = $splitRule->variants[1]->id;

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/a',
            'is_enabled' => true,
            'routing_rules' => [
                [...$split, 'id' => $splitRule->id, 'variants' => [
                    ['id' => $variantA, 'name' => 'A', 'destination_url' => 'https://example.com/va', 'weight' => 50],
                    ['id' => $variantZ, 'name' => 'Z', 'destination_url' => 'https://example.com/vz', 'weight' => 50],
                    ['name' => 'B', 'destination_url' => 'https://example.com/vb', 'weight' => 50],
                ]],
                $this->franceRule(),
                ['name' => 'Quiet', 'conditions' => [], 'destination_url' => 'https://example.com/quiet'],
            ],
        ])->assertOk();
        $rules = $this->link($id)->routingRules()->with('variants')->get();
        $variantB = $rules[0]->variants[2]->id;
        $franceId = $rules[1]->id;
        $quietId = $rules[2]->id;
        $ruleEvent = $this->analyticsEvent($this->link($id), ['routing_rule_id' => $franceId]);
        $variantEvent = $this->analyticsEvent($this->link($id), ['routing_rule_id' => $splitRule->id, 'routing_variant_id' => $variantB]);

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.notes.0', 'These routing rules and variants have analytics, so they stay on the link turned off instead of being deleted: B, France.');
        $this->restore($id, 1, 2)->assertOk();

        $this->assertDatabaseHas('analytics_events', ['id' => $ruleEvent->id, 'routing_rule_id' => $franceId]);
        $this->assertDatabaseHas('analytics_events', ['id' => $variantEvent->id, 'routing_rule_id' => $splitRule->id, 'routing_variant_id' => $variantB]);
        $this->assertDatabaseHas('routing_rules', ['id' => $franceId, 'is_enabled' => false]);
        $this->assertDatabaseHas('routing_variants', ['id' => $variantB, 'is_enabled' => false]);
        $this->assertDatabaseHas('routing_variants', ['id' => $variantA, 'is_enabled' => true]);
        $this->assertDatabaseMissing('routing_rules', ['id' => $quietId]);
        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.reason', 'This revision matches the current configuration.');
    }

    public function test_query_strings_and_fragments_never_reach_history(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, [
            'destination_url' => 'https://example.com/p?token=s3cr3t-value&utm=mail#access=frag-secret',
            'routing_rules' => [[...$this->franceRule(), 'destination_url' => 'https://example.com/fr?key=rule-secret']],
        ]);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/p?token=rotated-value&utm=mail#access=frag-secret',
            'is_enabled' => true,
        ])->assertOk()->assertJsonPath('data.revision', 2);

        $stored = ShortLinkRevision::query()->get()->toJson(JSON_UNESCAPED_UNICODE);
        foreach (['s3cr3t-value', 'rotated-value', 'frag-secret', 'rule-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }

        $this->getJson("/api/v1/links/$id/revisions/2")
            ->assertJsonPath('data.changes.0.field', 'destination_url')
            ->assertJsonPath('data.changes.0.after', 'https://example.com/p?token=•••&utm=•••#•••')
            ->assertJsonPath('data.snapshot.routing_rules.0.destination_url', 'https://example.com/fr?key=•••');
    }

    public function test_a_redacted_url_is_restored_only_while_the_link_still_uses_it(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $secretUrl = 'https://example.com/p?token=s3cr3t';
        $id = $this->createLink($domain->id, ['destination_url' => $secretUrl]);
        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/q',
            'fallback_url' => $secretUrl,
            'is_enabled' => true,
        ])->assertOk();

        $this->restore($id, 1, 2)->assertOk()->assertJsonPath('data.destination_url', $secretUrl);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/elsewhere',
            'fallback_url' => null,
            'is_enabled' => true,
        ])->assertOk();
        $reason = 'History keeps no query strings or fragments, and this revision used https://example.com/p?token=••• with one the link no longer has. Edit the link to restore that URL by hand.';

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonPath('data.restore.restorable', false)
            ->assertJsonPath('data.restore.reason', $reason);
        $this->restore($id, 1, 4)->assertUnprocessable()->assertJsonPath('errors.revision.0', $reason);
        $this->assertSame('https://example.com/elsewhere', $this->link($id)->destination_url);
    }

    public function test_api_clients_can_use_etags_to_detect_concurrent_updates(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);
        $payload = ['destination_url' => 'https://example.com/b', 'is_enabled' => true];

        $this->getJson("/api/v1/links/$id")->assertOk()->assertHeader('ETag', '"1"');
        $this->patchJson("/api/v1/links/$id", $payload, ['If-Match' => '"0"'])->assertStatus(412);
        $this->postJson("/api/v1/links/$id/archive", [], ['If-Match' => '"0"'])->assertStatus(412);
        $this->assertSame('https://example.com/a', $this->link($id)->destination_url);

        $this->patchJson("/api/v1/links/$id", $payload, ['If-Match' => '"1"'])->assertOk()->assertHeader('ETag', '"2"');
        $this->postJson("/api/v1/links/$id/revisions/1/restore", [], ['If-Match' => '"2"'])->assertOk()->assertHeader('ETag', '"3"');
        $this->patchJson("/api/v1/links/$id", ['destination_url' => 'https://example.com/c', 'is_enabled' => true])
            ->assertOk()
            ->assertHeader('ETag', '"4"');
    }

    public function test_links_without_history_get_a_baseline_before_their_first_change(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $legacy = $this->shortLink($workspace, $domain, 'legacy', ['destination_url' => 'https://example.com/original']);
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/links/$legacy->id")->assertJsonPath('data.revision', 0);
        $this->updateDestination($legacy->id, 'https://example.com/changed', 0);

        $this->getJson("/api/v1/links/$legacy->id/revisions")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.1.number', 1)
            ->assertJsonPath('data.1.action', 'baseline')
            ->assertJsonPath('data.1.channel', 'system')
            ->assertJsonPath('data.1.actor', null)
            ->assertJsonPath('data.1.changes', []);

        $this->restore($legacy->id, 1, 2)->assertOk()->assertJsonPath('data.destination_url', 'https://example.com/original');
    }

    public function test_deleting_a_folder_records_a_revision_for_each_link_inside_it(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring']);
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['folder_id' => $folder->id]);

        $this->deleteJson("/api/v1/folders/$folder->id")->assertOk();

        $this->getJson("/api/v1/links/$id/revisions")
            ->assertJsonPath('data.0.action', 'moved')
            ->assertJsonPath('data.0.actor.id', $owner->id)
            ->assertJsonPath('data.0.changes.0.before.name', 'Spring')
            ->assertJsonPath('data.0.changes.0.after', null);
        $this->assertNull($this->link($id)->folder_id);
    }

    public function test_a_renamed_folder_is_not_a_restore_change(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring']);
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['folder_id' => $folder->id]);
        $this->patchJson("/api/v1/links/$id", ['destination_url' => 'https://example.com/b', 'is_enabled' => true, 'folder_id' => $folder->id])->assertOk();
        $folder->update(['name' => 'Summer']);

        $this->getJson("/api/v1/links/$id/revisions/1")
            ->assertJsonCount(1, 'data.restore.changes')
            ->assertJsonPath('data.restore.changes.0.field', 'destination_url');
    }

    public function test_replacing_a_password_is_recorded_without_any_secret(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id, ['password' => 'first-secret']);

        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => 'https://example.com/a',
            'is_enabled' => true,
            'password' => 'second-secret',
        ])->assertOk();

        $this->getJson("/api/v1/links/$id/revisions/2")
            ->assertJsonPath('data.changes', [['field' => 'password_changed', 'before' => null, 'after' => null]]);
        $stored = ShortLinkRevision::query()->get()->toJson();
        $this->assertStringNotContainsString('second-secret', $stored);
        $this->assertStringNotContainsString((string) $this->link($id)->password_hash, $stored);
    }

    public function test_restore_ignores_folders_and_routing_rules_from_other_workspaces_and_links(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        [$otherWorkspace, $otherDomain] = $this->workspaceWithActiveDomain('other.test');
        $foreignFolder = Folder::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Theirs']);
        $foreignLink = $this->shortLink($otherWorkspace, $otherDomain, 'theirs');
        $foreignRule = $foreignLink->routingRules()->create(['name' => 'Theirs', 'destination_url' => 'https://example.com/theirs', 'conditions' => []]);
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);
        $this->updateDestination($id, 'https://example.com/b', 1);

        $revision = ShortLinkRevision::query()->where('short_link_id', $id)->where('number', 1)->firstOrFail();
        $revision->update(['snapshot' => [
            ...$revision->snapshot,
            'folder' => ['id' => $foreignFolder->id, 'name' => 'Theirs'],
            'routing_rules' => [['id' => $foreignRule->id, 'name' => 'Mine', 'type' => 'conditional', 'is_enabled' => true, 'match_mode' => 'all', 'conditions' => [], 'destination_url' => 'https://example.com/mine', 'variants' => []]],
        ]]);

        $this->restore($id, 1, 2)->assertOk()->assertJsonPath('data.folder', null);

        $this->assertNotSame($foreignRule->id, $this->link($id)->routingRules()->value('id'));
        $this->assertDatabaseHas('routing_rules', ['id' => $foreignRule->id, 'short_link_id' => $foreignLink->id, 'name' => 'Theirs']);
        $this->assertNull($foreignFolder->fresh()->shortLinks()->first());
    }

    public function test_dashboard_restore_redirects_back_and_records_the_dashboard_channel(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->actingInWorkspace($owner, $workspace);
        $this->post('/short-links', ['domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/a'])->assertRedirect();
        $link = ShortLink::query()->firstOrFail();
        $this->patch("/short-links/$link->id", ['destination_url' => 'https://example.com/b', 'is_enabled' => true, 'expected_revision' => 1])->assertRedirect();

        $this->post("/short-links/$link->id/revisions/1/restore", ['expected_revision' => 2])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('https://example.com/a', $link->fresh()->destination_url);
        $this->getJson("/short-links/$link->id/revisions/3")
            ->assertOk()
            ->assertJsonPath('data.channel', 'dashboard')
            ->assertJsonPath('data.actor.name', $owner->name);
    }

    public function test_history_is_deleted_with_its_short_link(): void
    {
        [, $domain, $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);
        $id = $this->createLink($domain->id);

        $this->deleteJson("/api/v1/links/$id")->assertOk();

        $this->assertSame(0, ShortLinkRevision::query()->count());
    }

    public function test_routing_editor_saves_bulk_status_changes_and_bulk_tags_each_record_a_revision(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->actingInWorkspace($owner, $workspace);

        $this->post('/short-links', ['domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/a'])->assertRedirect();
        $link = ShortLink::query()->firstOrFail();

        $this->put(route('short-links.routing.update', $link), [
            'destination_url' => 'https://example.com/a',
            'routing_rules' => [$this->franceRule()],
        ])->assertRedirect();
        $this->assertSame(2, $link->fresh()->revision);

        $this->post(route('short-links.bulk'), ['action' => 'disable', 'ids' => [$link->id]])->assertRedirect();
        $this->assertSame(3, $link->fresh()->revision);

        $this->post(route('short-links.bulk'), ['action' => 'add_tags', 'ids' => [$link->id], 'tags' => ['spring']])->assertRedirect();
        $this->assertSame(4, $link->fresh()->revision);

        $actions = ShortLinkRevision::query()->where('short_link_id', $link->id)->orderBy('number')->get();
        $this->assertSame(['created', 'updated', 'updated', 'updated'], $actions->map(fn (ShortLinkRevision $revision) => $revision->action->value)->all());
        $this->assertSame('routing_rules', $actions[1]->changes[0]['field']);
        $this->assertSame('is_enabled', $actions[2]->changes[0]['field']);
        $this->assertSame('tags', $actions[3]->changes[0]['field']);
    }

    private function createLink(int $domainId, array $attributes = []): int
    {
        return $this->postJson('/api/v1/links', [
            'domain_id' => $domainId,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/a',
            ...$attributes,
        ])->assertCreated()->json('data.id');
    }

    private function updateDestination(int $id, string $destination, int $expectedRevision): void
    {
        $this->patchJson("/api/v1/links/$id", [
            'destination_url' => $destination,
            'is_enabled' => true,
            'expected_revision' => $expectedRevision,
        ])->assertOk();
    }

    private function restore(int $id, int $number, int $expectedRevision): TestResponse
    {
        return $this->postJson("/api/v1/links/$id/revisions/$number/restore", ['expected_revision' => $expectedRevision]);
    }

    private function franceRule(): array
    {
        return [
            'name' => 'France',
            'conditions' => [['type' => 'country', 'operator' => 'is', 'value' => 'FR']],
            'destination_url' => 'https://example.com/fr',
        ];
    }

    private function link(int $id): ShortLink
    {
        return ShortLink::query()->findOrFail($id);
    }
}
