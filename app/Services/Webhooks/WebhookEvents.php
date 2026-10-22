<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\ShortLink;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookEvents
{
    public const VERSION = 1;

    public function __construct(private readonly ShortLinkEventData $shortLinks) {}

    public function shortLink(WebhookEventType $type, ShortLink $shortLink): void
    {
        $endpoints = WebhookEndpoint::query()
            ->where('workspace_id', $shortLink->workspace_id)
            ->where('is_enabled', true)
            ->whereJsonContains('events', $type->value)
            ->orderBy('id')
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        $this->record(
            $endpoints,
            $type,
            $shortLink->workspace_id,
            ['type' => 'short_link', 'id' => $shortLink->id],
            $this->shortLinks->handle($shortLink),
        );
    }

    public function test(WebhookEndpoint $endpoint): WebhookDelivery
    {
        return $this->record(
            collect([$endpoint]),
            WebhookEventType::Test,
            $endpoint->workspace_id,
            ['type' => 'webhook_endpoint', 'id' => $endpoint->id],
            ['message' => 'This is a test event from Openlink.'],
        )->sole();
    }

    private function record(Collection $endpoints, WebhookEventType $type, int $workspaceId, array $resource, array $data): Collection
    {
        $eventId = (string) Str::uuid7();
        $payload = json_encode([
            'id' => $eventId,
            'type' => $type->value,
            'version' => self::VERSION,
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'workspace' => ['id' => $workspaceId],
            'resource' => $resource,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $deliveries = $endpoints->map(fn (WebhookEndpoint $endpoint) => WebhookDelivery::create([
            'workspace_id' => $workspaceId,
            'webhook_endpoint_id' => $endpoint->id,
            'event_id' => $eventId,
            'event_type' => $type,
            'payload' => $payload,
            'status' => WebhookDeliveryStatus::Pending,
        ]));

        DB::afterCommit(fn () => $deliveries->each(fn (WebhookDelivery $delivery) => DeliverWebhook::dispatch($delivery->id)));

        return $deliveries;
    }
}
