<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WorkspaceMember;
use App\Services\Dns\DnsLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesWebhookEndpoints;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class WebhookEndpointManagementTest extends TestCase
{
    use CreatesWebhookEndpoints;
    use CreatesWorkspaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DnsLookup::class, (new InMemoryDnsLookup)
            ->pointTo('hooks.example.com', '93.184.215.14')
            ->pointTo('intranet.example.com', '10.0.0.5'));
    }

    public function test_owner_creates_an_endpoint_and_sees_the_signing_secret_once(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();

        $response = $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-endpoints.store'), [
                'url' => 'https://hooks.example.com/openlink',
                'events' => ['short_link.created', 'short_link.archived'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $endpoint = WebhookEndpoint::query()->sole();
        $secret = session('inertia.flash_data.webhookSecret.secret');

        $this->assertSame($workspace->id, $endpoint->workspace_id);
        $this->assertSame(['short_link.created', 'short_link.archived'], $endpoint->events);
        $this->assertTrue($endpoint->is_enabled);
        $this->assertMatchesRegularExpression('/^whsec_[A-Za-z0-9_-]{43}$/', $secret);
        $this->assertSame($secret, $endpoint->secret);
        $this->assertSame($endpoint->id, session('inertia.flash_data.webhookSecret.endpoint_id'));
        $this->assertNotSame($secret, DB::table('webhook_endpoints')->value('secret'));
        $this->assertStringNotContainsString($secret, DB::table('webhook_endpoints')->value('secret'));
        $this->assertArrayNotHasKey('secret', $endpoint->toArray());

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('settings.webhooks'))
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('webhookSecret.secret', $secret));

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('settings.webhooks'))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Webhooks/Index')
                ->missingFlash('webhookSecret')
                ->where('endpoints.0.id', $endpoint->id)
                ->where('endpoints.0.url', 'https://hooks.example.com/openlink')
                ->missing('endpoints.0.secret')
                ->where('eventTypes.0.value', 'short_link.created'));
    }

    public function test_endpoint_inputs_are_validated(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $cases = [
            ['url' => 'http://hooks.example.com/openlink', 'events' => ['short_link.created']],
            ['url' => 'https://intranet.example.com/hook', 'events' => ['short_link.created']],
            ['url' => 'https://169.254.169.254/latest', 'events' => ['short_link.created']],
            ['url' => 'https://user:pass@hooks.example.com/hook', 'events' => ['short_link.created']],
            ['url' => 'not a url', 'events' => ['short_link.created']],
            ['url' => 'https://2130706433/hook', 'events' => ['short_link.created']],
        ];

        foreach ($cases as $input) {
            $this->actingInWorkspace($owner, $workspace)
                ->post(route('webhook-endpoints.store'), $input)
                ->assertSessionHasErrors('url');
        }

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-endpoints.store'), ['url' => 'https://hooks.example.com/openlink', 'events' => []])
            ->assertSessionHasErrors('events');
        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-endpoints.store'), ['url' => 'https://hooks.example.com/openlink', 'events' => ['webhook.test', 'link.deleted']])
            ->assertSessionHasErrors(['events.0', 'events.1']);

        $this->assertDatabaseCount('webhook_endpoints', 0);
    }

    public function test_owner_updates_disables_and_rotates_the_secret(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('webhook-endpoints.update', $endpoint), [
                'url' => 'https://hooks.example.com/v2',
                'events' => ['short_link.updated'],
                'is_enabled' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('inertia.flash_data.webhookSecret');

        $endpoint->refresh();
        $this->assertSame('https://hooks.example.com/v2', $endpoint->url);
        $this->assertSame(['short_link.updated'], $endpoint->events);
        $this->assertFalse($endpoint->is_enabled);
        $this->assertSame('whsec_test_secret', $endpoint->secret);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-endpoints.rotate-secret', $endpoint))
            ->assertRedirect();

        $rotated = session('inertia.flash_data.webhookSecret.secret');
        $this->assertNotSame('whsec_test_secret', $rotated);
        $this->assertSame($rotated, $endpoint->refresh()->secret);
    }

    public function test_sending_a_test_event_queues_a_test_delivery(): void
    {
        Queue::fake();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace, ['events' => ['short_link.archived']]);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-endpoints.test', $endpoint))
            ->assertRedirect();

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame('webhook.test', $delivery->event_type->value);
        $this->assertSame(['type' => 'webhook_endpoint', 'id' => $endpoint->id], json_decode($delivery->payload, true)['resource']);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->deliveryId === $delivery->id);
    }

    public function test_failed_deliveries_can_be_retried_with_the_same_event_id(): void
    {
        Queue::fake();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace);
        $failed = $this->delivery($endpoint, WebhookDeliveryStatus::Failed);
        $succeeded = $this->delivery($endpoint, WebhookDeliveryStatus::Succeeded, 'ok');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-deliveries.retry', $failed))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $failed->refresh();
        $this->assertSame(WebhookDeliveryStatus::Pending, $failed->status);
        $this->assertSame('0199f0a4-7c1d-7b3a-9e55-2f3b8d1c4a10', $failed->event_id);
        $this->assertSame(0, $failed->attempts);
        $this->assertNull($failed->error);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job) => $job->deliveryId === $failed->id);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-deliveries.retry', $succeeded))
            ->assertSessionHasErrors('delivery');

        $endpoint->update(['is_enabled' => false]);
        $this->actingInWorkspace($owner, $workspace)
            ->post(route('webhook-deliveries.retry', $this->delivery($endpoint, WebhookDeliveryStatus::Failed, 'other')))
            ->assertSessionHasErrors('delivery');

        Queue::assertPushed(DeliverWebhook::class, 1);
    }

    public function test_deleting_an_endpoint_removes_its_delivery_history(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace);
        $this->delivery($endpoint, WebhookDeliveryStatus::Succeeded);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('webhook-endpoints.destroy', $endpoint))
            ->assertRedirect();

        $this->assertDatabaseCount('webhook_endpoints', 0);
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_only_owners_and_admins_manage_endpoints(): void
    {
        Queue::fake();
        [$workspace] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace);
        $delivery = $this->delivery($endpoint, WebhookDeliveryStatus::Failed);
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingInWorkspace($admin, $workspace)->get(route('settings.webhooks'))->assertOk();
        $this->actingInWorkspace($admin, $workspace)
            ->post(route('webhook-endpoints.test', $endpoint))
            ->assertRedirect();

        foreach ([WorkspaceMember::ROLE_EDITOR, WorkspaceMember::ROLE_VIEWER] as $role) {
            $member = $this->memberOf($workspace, $role);

            $this->actingInWorkspace($member, $workspace)->get(route('settings.webhooks'))->assertRedirect(route('profile.edit'));
            $this->actingInWorkspace($member, $workspace)
                ->post(route('webhook-endpoints.store'), ['url' => 'https://hooks.example.com/x', 'events' => ['short_link.created']])
                ->assertForbidden();
            $this->actingInWorkspace($member, $workspace)
                ->post(route('webhook-endpoints.store'), ['url' => 'https://intranet.example.com/x', 'events' => ['short_link.created']])
                ->assertForbidden();
            $this->actingInWorkspace($member, $workspace)
                ->patch(route('webhook-endpoints.update', $endpoint), ['url' => 'https://hooks.example.com/x', 'events' => ['short_link.created'], 'is_enabled' => true])
                ->assertForbidden();
            $this->actingInWorkspace($member, $workspace)->post(route('webhook-endpoints.rotate-secret', $endpoint))->assertForbidden();
            $this->actingInWorkspace($member, $workspace)->post(route('webhook-endpoints.test', $endpoint))->assertForbidden();
            $this->actingInWorkspace($member, $workspace)->post(route('webhook-deliveries.retry', $delivery))->assertForbidden();
            $this->actingInWorkspace($member, $workspace)->delete(route('webhook-endpoints.destroy', $endpoint))->assertForbidden();
        }

        $this->assertSame('whsec_test_secret', $endpoint->refresh()->secret);
        $this->assertDatabaseCount('webhook_endpoints', 1);
        Queue::assertPushed(DeliverWebhook::class, 1);
    }

    public function test_endpoints_and_deliveries_of_another_workspace_are_out_of_reach(): void
    {
        Queue::fake();
        [$workspace, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $foreign = $this->webhookEndpoint($otherWorkspace, ['url' => 'https://hooks.example.com/foreign']);
        $foreignDelivery = $this->delivery($foreign, WebhookDeliveryStatus::Failed);
        $this->webhookEndpoint($workspace);

        $this->actingInWorkspace($owner, $workspace)->post(route('webhook-endpoints.rotate-secret', $foreign))->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->post(route('webhook-endpoints.test', $foreign))->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->delete(route('webhook-endpoints.destroy', $foreign))->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->post(route('webhook-deliveries.retry', $foreignDelivery))->assertForbidden();

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('settings.webhooks'))
            ->assertDontSee('https:\/\/hooks.example.com\/foreign', false)
            ->assertInertia(fn (AssertableInertia $page) => $page->has('endpoints', 1));

        Queue::assertNothingPushed();
    }

    public function test_settings_page_lists_bounded_sanitized_delivery_history(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace);

        foreach (range(1, 30) as $index) {
            $this->delivery($endpoint, WebhookDeliveryStatus::Failed, 'event-'.$index);
        }

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('settings.webhooks'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('endpoints.0.deliveries', 20)
                ->where('endpoints.0.deliveries.0.status', 'failed')
                ->where('endpoints.0.deliveries.0.error', 'The endpoint responded with HTTP 500.')
                ->where('endpoints.0.deliveries.0.response_status', 500)
                ->where('endpoints.0.deliveries.0.duration_ms', 120)
                ->missing('endpoints.0.deliveries.0.payload'));
    }

    private function delivery(WebhookEndpoint $endpoint, WebhookDeliveryStatus $status, string $suffix = ''): WebhookDelivery
    {
        return WebhookDelivery::create([
            'workspace_id' => $endpoint->workspace_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => $suffix === '' ? '0199f0a4-7c1d-7b3a-9e55-2f3b8d1c4a10' : (string) Str::uuid7(),
            'event_type' => 'short_link.created',
            'payload' => '{"id":"evt"}',
            'status' => $status,
            'attempts' => 6,
            'response_status' => 500,
            'error' => 'The endpoint responded with HTTP 500.',
            'duration_ms' => 120,
            'last_attempted_at' => now(),
        ]);
    }
}
