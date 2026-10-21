<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    private const MAX_BACKOFF_SECONDS = 21600;

    public int $timeout = 60;

    public function __construct(public readonly int $deliveryId) {}

    public static function maxAttempts(): int
    {
        return (int) config('openlink.webhooks.max_attempts');
    }

    public static function backoffAfter(int $attempts): int
    {
        return min(60 * 4 ** ($attempts - 1), self::MAX_BACKOFF_SECONDS);
    }

    public function tries(): int
    {
        return self::maxAttempts();
    }

    public function handle(WebhookSender $sender): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if (! $delivery || $delivery->status->isFinal()) {
            return;
        }

        if (! $delivery->endpoint->is_enabled) {
            $delivery->update([
                'status' => WebhookDeliveryStatus::Failed,
                'error' => 'The endpoint is disabled.',
                'next_attempt_at' => null,
            ]);

            return;
        }

        $attempt = $sender->send($delivery);
        $attempts = $delivery->attempts + 1;
        $retry = ! $attempt->succeeded && $attempt->retryable && $attempts < self::maxAttempts();
        $delay = $retry ? self::backoffAfter($attempts) : null;

        $delivery->update([
            'status' => match (true) {
                $attempt->succeeded => WebhookDeliveryStatus::Succeeded,
                $retry => WebhookDeliveryStatus::Retrying,
                default => WebhookDeliveryStatus::Failed,
            },
            'attempts' => $attempts,
            'response_status' => $attempt->responseStatus,
            'error' => $attempt->error,
            'duration_ms' => $attempt->durationMs,
            'last_attempted_at' => now(),
            'next_attempt_at' => $retry ? now()->addSeconds($delay) : null,
            'delivered_at' => $attempt->succeeded ? now() : null,
        ]);

        if ($retry) {
            $this->release($delay);
        }
    }

    public function failed(?Throwable $exception): void
    {
        WebhookDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereIn('status', [WebhookDeliveryStatus::Pending, WebhookDeliveryStatus::Retrying])
            ->update([
                'status' => WebhookDeliveryStatus::Failed,
                'error' => 'The delivery job stopped unexpectedly.',
                'next_attempt_at' => null,
            ]);
    }
}
