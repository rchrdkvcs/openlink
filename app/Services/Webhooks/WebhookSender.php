<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WebhookSender
{
    public function __construct(
        private readonly WebhookTargets $targets,
        private readonly WebhookSignature $signature,
    ) {}

    public function send(WebhookDelivery $delivery): WebhookAttempt
    {
        try {
            $target = $this->targets->resolve($delivery->endpoint->url);
        } catch (UnsafeWebhookTarget $exception) {
            return WebhookAttempt::failed($exception->getMessage(), retryable: false);
        }

        $timestamp = now()->getTimestamp();
        $started = hrtime(true);

        try {
            $response = Http::withOptions([
                'proxy' => '',
                'verify' => true,
                'curl' => [CURLOPT_RESOLVE => [$target->curlResolve()]],
            ])
                ->withoutRedirecting()
                ->connectTimeout((int) config('openlink.webhooks.connect_timeout'))
                ->timeout((int) config('openlink.webhooks.timeout'))
                ->withUserAgent('Openlink-Webhooks/1')
                ->withHeaders([
                    'Openlink-Event-Id' => $delivery->event_id,
                    'Openlink-Event-Type' => $delivery->event_type->value,
                    'Openlink-Timestamp' => (string) $timestamp,
                    'Openlink-Signature' => $this->signature->sign($delivery->endpoint->secret, $timestamp, $delivery->payload),
                ])
                ->withBody($delivery->payload, 'application/json')
                ->post($target->url);
        } catch (ConnectionException $exception) {
            return WebhookAttempt::failed(
                str_contains($exception->getMessage(), 'timed out')
                    ? 'The endpoint did not respond in time.'
                    : 'Could not connect to the endpoint.',
                durationMs: $this->elapsed($started),
            );
        }

        $duration = $this->elapsed($started);
        $status = $response->status();

        if ($response->successful()) {
            return WebhookAttempt::succeeded($status, $duration);
        }

        if ($response->redirect()) {
            return WebhookAttempt::failed('The endpoint redirected. Redirects are not followed.', $status, $duration, false);
        }

        return WebhookAttempt::failed("The endpoint responded with HTTP {$status}.", $status, $duration);
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
