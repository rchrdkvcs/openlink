<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Services\Dns\DnsLookup;
use App\Services\Webhooks\WebhookEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWebhookEndpoints;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use CreatesWebhookEndpoints;
    use CreatesWorkspaces;
    use RefreshDatabase;

    private InMemoryDnsLookup $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = (new InMemoryDnsLookup)->pointTo('hooks.example.com', '93.184.215.14');
        $this->app->instance(DnsLookup::class, $this->dns);
    }

    public function test_delivery_posts_the_exact_stored_body_with_a_timestamped_hmac_signature(): void
    {
        $this->travelTo(now()->setTimestamp(1_800_000_000));
        $delivery = $this->pendingDelivery();
        $captured = [];
        Http::fake(function (Request $request, array $options) use (&$captured) {
            $captured = ['request' => $request, 'options' => $options];

            return Http::response('ok', 200);
        });

        $this->deliver($delivery);

        $request = $captured['request'];
        $this->assertSame('https://hooks.example.com/openlink', $request->url());
        $this->assertSame('POST', $request->method());
        $this->assertSame($delivery->payload, $request->body());
        $this->assertSame('application/json', $request->header('Content-Type')[0]);
        $this->assertSame($delivery->event_id, $request->header('Openlink-Event-Id')[0]);
        $this->assertSame('short_link.created', $request->header('Openlink-Event-Type')[0]);
        $this->assertSame('1800000000', $request->header('Openlink-Timestamp')[0]);
        $this->assertSame(
            'v1='.hash_hmac('sha256', '1800000000.'.$delivery->payload, 'whsec_test_secret'),
            $request->header('Openlink-Signature')[0],
        );
        $this->assertSame(['hooks.example.com:443:93.184.215.14'], $captured['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($captured['options']['allow_redirects']);
        $this->assertTrue($captured['options']['verify']);

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Succeeded, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNull($delivery->error);
        $this->assertNotNull($delivery->duration_ms);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_failed_attempts_are_released_with_exponential_backoff_and_reuse_the_event_id(): void
    {
        $delivery = $this->pendingDelivery();
        $eventIds = [];
        Http::fake(function (Request $request) use (&$eventIds) {
            $eventIds[] = $request->header('Openlink-Event-Id')[0];

            return Http::response('<html>secret internals</html>', 503);
        });

        $first = $this->deliver($delivery);
        $first->assertReleased(60);
        $this->assertSame(WebhookDeliveryStatus::Retrying, $delivery->refresh()->status);
        $this->assertSame('The endpoint responded with HTTP 503.', $delivery->error);
        $this->assertSame(503, $delivery->response_status);
        $this->assertNotNull($delivery->next_attempt_at);

        $this->deliver($delivery)->assertReleased(240);
        $this->deliver($delivery)->assertReleased(960);

        $this->assertSame(3, $delivery->refresh()->attempts);
        $this->assertSame([$delivery->event_id], array_values(array_unique($eventIds)));
        $this->assertStringNotContainsString('secret internals', (string) $delivery->error);
    }

    public function test_attempts_are_bounded(): void
    {
        config(['openlink.webhooks.max_attempts' => 2]);
        $delivery = $this->pendingDelivery();
        Http::fake(fn () => Http::response('', 500));

        $this->deliver($delivery)->assertReleased();
        $this->deliver($delivery)->assertNotReleased();
        $this->deliver($delivery)->assertNotReleased();

        $delivery->refresh();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertNull($delivery->next_attempt_at);
        Http::assertSentCount(2);
    }

    public function test_connection_failures_are_recorded_without_raw_exception_details(): void
    {
        $delivery = $this->pendingDelivery();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds with 93.184.215.14'));

        $this->deliver($delivery)->assertReleased(60);

        $this->assertSame('The endpoint did not respond in time.', $delivery->refresh()->error);
        $this->assertNull($delivery->response_status);
    }

    public function test_redirects_are_not_followed(): void
    {
        $delivery = $this->pendingDelivery();
        Http::fake(fn () => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']));

        $this->deliver($delivery)->assertNotReleased();

        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->refresh()->status);
        $this->assertSame('The endpoint redirected. Redirects are not followed.', $delivery->error);
        Http::assertSentCount(1);
    }

    public function test_unsafe_targets_are_never_contacted(): void
    {
        Http::fake();
        $cases = [
            'https://127.0.0.1/hook' => [],
            'https://[::1]/hook' => [],
            'https://169.254.169.254/latest/meta-data' => [],
            'https://10.1.2.3/hook' => [],
            'https://[fd00::1]/hook' => [],
            'https://[::ffff:10.0.0.1]/hook' => [],
            'https://localhost/hook' => [],
            'https://metadata.google.internal/computeMetadata' => [],
            'https://private.example.com/hook' => ['private.example.com', '192.168.1.10'],
            'https://mixed.example.com/hook' => ['mixed.example.com', '93.184.215.14', '100.64.0.1'],
            'https://v6.example.com/hook' => ['v6.example.com', 'fe80::1'],
            'https://unresolved.example.com/hook' => [],
            'http://hooks.example.com/plain' => [],
            'https://2130706433/hook' => [],
            'https://0x7f.1/hook' => [],
            'https://１２７-0-0-1.example.com/hook' => [],
        ];

        foreach ($cases as $url => $records) {
            if ($records !== []) {
                $this->dns->pointTo(...$records);
            }

            $delivery = $this->pendingDelivery(['url' => $url]);
            $this->deliver($delivery)->assertNotReleased();

            $delivery->refresh();
            $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status, $url);
            $this->assertNull($delivery->response_status, $url);
        }

        Http::assertNothingSent();
    }

    public function test_dns_is_resolved_on_every_attempt_so_rebinding_cannot_reach_private_addresses(): void
    {
        $delivery = $this->pendingDelivery();
        Http::fake(fn () => Http::response('', 500));
        $this->deliver($delivery)->assertReleased();

        $this->dns->pointTo('hooks.example.com', '127.0.0.1');
        $this->deliver($delivery)->assertNotReleased();

        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->refresh()->status);
        $this->assertSame('The endpoint resolves to a private or reserved address.', $delivery->error);
        Http::assertSentCount(1);
    }

    public function test_disabled_endpoints_stop_new_attempts(): void
    {
        $delivery = $this->pendingDelivery();
        Http::fake(fn () => Http::response('', 500));
        $this->deliver($delivery)->assertReleased();

        $delivery->endpoint->update(['is_enabled' => false]);
        $this->deliver($delivery)->assertNotReleased();

        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->refresh()->status);
        $this->assertSame('The endpoint is disabled.', $delivery->error);
        $this->assertSame(1, $delivery->attempts);
        Http::assertSentCount(1);
    }

    public function test_deleted_endpoints_drop_their_queued_deliveries(): void
    {
        $delivery = $this->pendingDelivery();
        Http::fake();

        $delivery->endpoint->delete();
        $this->deliver($delivery)->assertNotReleased();

        $this->assertDatabaseCount('webhook_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_a_failing_endpoint_does_not_block_link_edits_or_public_redirects(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->webhookEndpoint($workspace);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'slug' => 'resilient',
            'destination_url' => 'https://example.com/resilient',
        ])->assertCreated();

        $this->get('https://go.example.test/resilient')->assertRedirect('https://example.com/resilient');

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame(WebhookDeliveryStatus::Retrying, $delivery->status);
        $this->assertSame('Could not connect to the endpoint.', $delivery->error);
    }

    public function test_queued_jobs_carry_only_the_delivery_reference(): void
    {
        Queue::fake();
        $delivery = $this->pendingDelivery();
        app(WebhookEvents::class)->test($delivery->endpoint);

        Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
            $serialized = serialize($job);

            return ! str_contains($serialized, 'whsec_test_secret') && ! str_contains($serialized, 'short_link');
        });
    }

    private function pendingDelivery(array $endpointAttributes = []): WebhookDelivery
    {
        [$workspace] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace, $endpointAttributes);

        return WebhookDelivery::create([
            'workspace_id' => $workspace->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => '0199f0a4-7c1d-7b3a-9e55-2f3b8d1c4a10',
            'event_type' => 'short_link.created',
            'payload' => '{"id":"0199f0a4-7c1d-7b3a-9e55-2f3b8d1c4a10","type":"short_link.created","data":{"slug":"café"}}',
            'status' => WebhookDeliveryStatus::Pending,
        ]);
    }

    private function deliver(WebhookDelivery $delivery): DeliverWebhook
    {
        $job = (new DeliverWebhook($delivery->id))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        return $job;
    }
}
