<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookSender
{
    public const MAX_RESPONSE_BYTES = 65536;

    private const RETRYABLE_CLIENT_ERRORS = [408, 425, 429];

    public function __construct(
        private readonly WebhookTargets $targets,
        private readonly WebhookSignature $signature,
    ) {}

    public function send(WebhookDelivery $delivery): WebhookAttempt
    {
        try {
            $target = $this->targets->resolve($delivery->endpoint->url);
        } catch (UnsafeWebhookTarget $exception) {
            return WebhookAttempt::failed($exception->getMessage(), retryable: $exception->retryable);
        }

        $timestamp = now()->getTimestamp();
        $started = hrtime(true);
        $oversized = false;

        try {
            $response = Http::withOptions([
                'proxy' => '',
                'verify' => true,
                'curl' => [CURLOPT_RESOLVE => [$target->curlResolve()]],
                'progress' => function (int $downloadTotal, int $downloaded) use (&$oversized): bool {
                    $oversized = $oversized || $downloaded > self::MAX_RESPONSE_BYTES;

                    return $oversized;
                },
            ])
                ->withoutRedirecting()
                ->connectTimeout((int) config('openlink.webhooks.connect_timeout'))
                ->timeout((int) config('openlink.webhooks.timeout'))
                ->withUserAgent('Openlink-Webhooks/1')
                ->withHeaders([
                    'Openlink-Event-Id' => $delivery->event_id,
                    'Openlink-Event-Type' => $delivery->event_type->value,
                    'Openlink-Timestamp' => (string) $timestamp,
                    'Openlink-Signature' => $this->signature->header($delivery->endpoint->signingSecrets(), $timestamp, $delivery->payload),
                ])
                ->withBody($delivery->payload, 'application/json')
                ->post($target->url);
        } catch (Throwable $exception) {
            return $this->transportFailure($exception, $oversized, $this->elapsed($started));
        }

        return $this->outcome($response, $this->elapsed($started));
    }

    private function outcome(Response $response, int $duration): WebhookAttempt
    {
        $status = $response->status();

        if ($response->successful()) {
            return WebhookAttempt::succeeded($status, $duration);
        }

        if ($response->redirect()) {
            return WebhookAttempt::failed('The endpoint redirected. Redirects are not followed.', $status, $duration, false);
        }

        return WebhookAttempt::failed(
            "The endpoint responded with HTTP {$status}.",
            $status,
            $duration,
            $status >= 500 || in_array($status, self::RETRYABLE_CLIENT_ERRORS, true),
        );
    }

    private function transportFailure(Throwable $exception, bool $oversized, int $duration): WebhookAttempt
    {
        if ($oversized) {
            return WebhookAttempt::failed('The endpoint response exceeded 64 KB.', durationMs: $duration, retryable: false);
        }

        if (! $exception instanceof ConnectionException) {
            return WebhookAttempt::failed('The request to the endpoint failed.', durationMs: $duration);
        }

        return WebhookAttempt::failed(
            str_contains($exception->getMessage(), 'timed out')
                ? 'The endpoint did not respond in time.'
                : 'Could not connect to the endpoint.',
            durationMs: $duration,
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
