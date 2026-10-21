<?php

namespace Tests\Support;

use App\Enums\WebhookEventType;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;

trait CreatesWebhookEndpoints
{
    protected function webhookEndpoint(Workspace $workspace, array $attributes = []): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'workspace_id' => $workspace->id,
            'url' => 'https://hooks.example.com/openlink',
            'events' => WebhookEventType::subscribableValues(),
            'secret' => 'whsec_test_secret',
            'is_enabled' => true,
            ...$attributes,
        ]);
    }
}
