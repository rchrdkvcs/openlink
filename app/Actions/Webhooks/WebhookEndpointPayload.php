<?php

namespace App\Actions\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class WebhookEndpointPayload
{
    public const HISTORY_LIMIT = 20;

    public static function forWorkspace(Workspace $workspace): Collection
    {
        return $workspace->webhookEndpoints()
            ->orderBy('id')
            ->get()
            ->map(fn (WebhookEndpoint $endpoint) => self::make($endpoint));
    }

    public static function make(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'events' => $endpoint->events,
            'is_enabled' => $endpoint->is_enabled,
            'created_at' => $endpoint->created_at,
            'deliveries' => $endpoint->deliveries()
                ->latest('id')
                ->limit(self::HISTORY_LIMIT)
                ->get()
                ->map(fn (WebhookDelivery $delivery) => [
                    'id' => $delivery->id,
                    'event_id' => $delivery->event_id,
                    'event_type' => $delivery->event_type->value,
                    'status' => $delivery->status->value,
                    'attempts' => $delivery->attempts,
                    'response_status' => $delivery->response_status,
                    'error' => $delivery->error,
                    'duration_ms' => $delivery->duration_ms,
                    'last_attempted_at' => $delivery->last_attempted_at,
                    'next_attempt_at' => $delivery->next_attempt_at,
                    'created_at' => $delivery->created_at,
                ])
                ->values(),
        ];
    }
}
