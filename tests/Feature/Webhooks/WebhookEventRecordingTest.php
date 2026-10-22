<?php

namespace Tests\Feature\Webhooks;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Support\CreatesWebhookEndpoints;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class WebhookEventRecordingTest extends TestCase
{
    use CreatesWebhookEndpoints;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_creating_a_short_link_records_a_versioned_event_and_queues_delivery_after_commit(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $endpoint = $this->webhookEndpoint($workspace);
        Sanctum::actingAs($owner);

        $linkId = $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
            'password' => 'hunter22',
            'tags' => 'spring',
        ])->assertCreated()->json('data.id');

        $delivery = WebhookDelivery::query()->sole();
        $envelope = json_decode($delivery->payload, true);

        $this->assertSame($endpoint->id, $delivery->webhook_endpoint_id);
        $this->assertSame($workspace->id, $delivery->workspace_id);
        $this->assertSame('pending', $delivery->status->value);
        $this->assertTrue(Str::isUuid($envelope['id']));
        $this->assertSame($delivery->event_id, $envelope['id']);
        $this->assertSame('short_link.created', $envelope['type']);
        $this->assertSame(1, $envelope['version']);
        $this->assertNotEmpty($envelope['occurred_at']);
        $this->assertSame(['id' => $workspace->id], $envelope['workspace']);
        $this->assertSame(['type' => 'short_link', 'id' => $linkId], $envelope['resource']);
        $this->assertSame('launch', $envelope['data']['slug']);
        $this->assertSame('https://go.example.test/launch', $envelope['data']['short_url']);
        $this->assertSame('https://example.com/launch', $envelope['data']['destination_url']);
        $this->assertSame(['spring'], array_column($envelope['data']['tags'], 'name'));
        $this->assertTrue($envelope['data']['has_password']);
        $this->assertStringNotContainsString('hunter22', $delivery->payload);
        $this->assertStringNotContainsString('password_hash', $delivery->payload);
        $this->assertStringNotContainsString('whsec_test_secret', $delivery->payload);

        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->deliveryId === $delivery->id);
    }

    public function test_rolled_back_mutations_record_and_queue_nothing(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->webhookEndpoint($workspace);

        try {
            DB::transaction(function () use ($owner, $workspace, $domain) {
                app(ShortLinkMutation::class)->create($owner, $workspace, [
                    'domain_id' => $domain->id,
                    'slug' => 'doomed',
                    'destination_url' => 'https://example.com/doomed',
                ]);

                throw new RuntimeException('Abort');
            });
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('short_links', 0);
        $this->assertDatabaseCount('webhook_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_updates_moves_and_archives_record_their_event_types(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->webhookEndpoint($workspace);
        $link = $this->shortLink($workspace, $domain, 'spring');
        $folder = $workspace->folders()->create(['name' => 'Campaigns']);
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/links/'.$link->id, [
            'slug' => 'summer',
            'destination_url' => 'https://example.com/summer',
            'is_enabled' => true,
        ])->assertOk();
        $this->postJson('/api/v1/links/'.$link->id.'/move', ['folder_id' => $folder->id])->assertOk();
        $this->postJson('/api/v1/links/'.$link->id.'/archive')->assertOk();

        $deliveries = WebhookDelivery::query()->orderBy('id')->get();

        $this->assertSame(
            ['short_link.updated', 'short_link.updated', 'short_link.archived'],
            $deliveries->map(fn (WebhookDelivery $delivery) => $delivery->event_type->value)->all(),
        );
        $this->assertCount(3, $deliveries->pluck('event_id')->unique());
        $this->assertSame('summer', json_decode($deliveries[0]->payload, true)['data']['slug']);
        $this->assertSame(['id' => $folder->id, 'name' => 'Campaigns'], json_decode($deliveries[1]->payload, true)['data']['folder']);
        $this->assertNotNull(json_decode($deliveries[2]->payload, true)['data']['archived_at']);
        Queue::assertPushed(DeliverWebhook::class, 3);
    }

    public function test_bulk_actions_record_one_update_event_per_link(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->webhookEndpoint($workspace);
        $first = $this->shortLink($workspace, $domain, 'spring');
        $second = $this->shortLink($workspace, $domain, 'summer');
        $this->actingInWorkspace($owner, $workspace);

        $this->post(route('short-links.bulk'), ['action' => 'add_tags', 'ids' => [$first->id, $second->id], 'tags' => ['launch']])->assertRedirect();
        $this->post(route('short-links.bulk'), ['action' => 'disable', 'ids' => [$first->id, $second->id]])->assertRedirect();

        $deliveries = WebhookDelivery::query()->orderBy('id')->get();

        $this->assertSame(array_fill(0, 4, 'short_link.updated'), $deliveries->map(fn (WebhookDelivery $delivery) => $delivery->event_type->value)->all());
        $this->assertSame(['launch'], array_column(json_decode($deliveries[0]->payload, true)['data']['tags'], 'name'));
        $this->assertFalse(json_decode($deliveries[3]->payload, true)['data']['is_enabled']);
    }

    public function test_dashboard_mutations_record_the_same_events_as_the_api(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->webhookEndpoint($workspace);
        $link = $this->shortLink($workspace, $domain, 'spring');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.archive', $link))
            ->assertRedirect();

        $this->assertSame('short_link.archived', WebhookDelivery::query()->sole()->event_type->value);
    }

    public function test_only_enabled_subscribed_endpoints_of_the_same_workspace_receive_events(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $subscribed = $this->webhookEndpoint($workspace);
        $this->webhookEndpoint($workspace, ['events' => ['short_link.archived']]);
        $this->webhookEndpoint($workspace, ['is_enabled' => false]);
        $this->webhookEndpoint($otherWorkspace);
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        Sanctum::actingAs($editor);

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'destination_url' => 'https://example.com/editor',
        ])->assertCreated();

        $this->assertSame([$subscribed->id], WebhookDelivery::query()->pluck('webhook_endpoint_id')->all());
    }

    public function test_mutations_without_endpoints_record_nothing(): void
    {
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/links', ['domain_id' => $domain->id, 'destination_url' => 'https://example.com'])->assertCreated();

        $this->assertDatabaseCount('webhook_deliveries', 0);
        Queue::assertNothingPushed();
    }
}
