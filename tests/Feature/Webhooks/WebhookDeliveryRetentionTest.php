<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesWebhookEndpoints;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class WebhookDeliveryRetentionTest extends TestCase
{
    use CreatesWebhookEndpoints;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_deliveries_older_than_the_retention_window_are_pruned(): void
    {
        config(['openlink.webhooks.retention_days' => 30]);
        [$workspace] = $this->workspaceWithOwner();
        $endpoint = $this->webhookEndpoint($workspace);
        $old = $this->delivery($endpoint, now()->subDays(31));
        $recent = $this->delivery($endpoint, now()->subDays(29));

        $this->artisan('openlink:prune-webhook-deliveries')
            ->expectsOutput('Pruned 1 webhook deliveries older than 30 days.')
            ->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($endpoint);
    }

    private function delivery(WebhookEndpoint $endpoint, $createdAt): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'workspace_id' => $endpoint->workspace_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => (string) Str::uuid7(),
            'event_type' => 'short_link.created',
            'payload' => '{}',
            'status' => WebhookDeliveryStatus::Succeeded,
        ]);
        $delivery->forceFill(['created_at' => $createdAt])->save();

        return $delivery;
    }
}
