<?php

namespace App\Actions\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;
use App\Services\Webhooks\WebhookEvents;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebhookEndpointMutation
{
    public function __construct(
        private readonly Gate $gate,
        private readonly WebhookEvents $events,
    ) {}

    public static function limitMessage(): string
    {
        return 'A workspace can have up to '.WebhookEndpoint::MAX_PER_WORKSPACE.' webhook endpoints.';
    }

    private static function generateSecret(): string
    {
        return 'whsec_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function create(User $user, Workspace $workspace, string $url, array $events): WebhookEndpoint
    {
        $this->gate->forUser($user)->authorize('manage', $workspace);

        return DB::transaction(function () use ($workspace, $url, $events): WebhookEndpoint {
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->first();

            if (WebhookEndpoint::limitReachedIn($workspace)) {
                throw ValidationException::withMessages(['url' => self::limitMessage()]);
            }

            return WebhookEndpoint::create([
                'workspace_id' => $workspace->id,
                'url' => $url,
                'events' => array_values($events),
                'secret' => self::generateSecret(),
                'is_enabled' => true,
            ]);
        });
    }

    public function update(User $user, WebhookEndpoint $endpoint, string $url, array $events, bool $isEnabled): WebhookEndpoint
    {
        $this->authorize($user, $endpoint);

        $endpoint->update([
            'url' => $url,
            'events' => array_values($events),
            'is_enabled' => $isEnabled,
        ]);

        return $endpoint;
    }

    public function rotateSecret(User $user, WebhookEndpoint $endpoint): string
    {
        $this->authorize($user, $endpoint);

        $secret = self::generateSecret();
        $endpoint->update([
            'previous_secret' => $endpoint->secret,
            'previous_secret_expires_at' => now()->addHours(WebhookEndpoint::SECRET_OVERLAP_HOURS),
            'secret' => $secret,
        ]);

        return $secret;
    }

    public function sendTest(User $user, WebhookEndpoint $endpoint): WebhookDelivery
    {
        $this->authorize($user, $endpoint);
        $this->ensureEnabled($endpoint);

        return $this->events->test($endpoint);
    }

    public function retry(User $user, WebhookDelivery $delivery): WebhookDelivery
    {
        $this->authorize($user, $delivery->endpoint);

        if ($delivery->status !== WebhookDeliveryStatus::Failed) {
            throw ValidationException::withMessages(['delivery' => 'Only failed deliveries can be retried.']);
        }

        $this->ensureEnabled($delivery->endpoint);

        $delivery->update([
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'error' => null,
            'next_attempt_at' => null,
        ]);

        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }

    public function delete(User $user, WebhookEndpoint $endpoint): void
    {
        $this->authorize($user, $endpoint);
        $endpoint->delete();
    }

    private function authorize(User $user, WebhookEndpoint $endpoint): void
    {
        $this->gate->forUser($user)->authorize('manage', $endpoint->workspace);
    }

    private function ensureEnabled(WebhookEndpoint $endpoint): void
    {
        if (! $endpoint->is_enabled) {
            throw ValidationException::withMessages(['delivery' => 'Enable the endpoint before sending deliveries.']);
        }
    }
}
