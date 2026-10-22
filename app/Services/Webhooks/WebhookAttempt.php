<?php

namespace App\Services\Webhooks;

final readonly class WebhookAttempt
{
    public function __construct(
        public bool $succeeded,
        public ?int $responseStatus,
        public ?string $error,
        public ?int $durationMs,
        public bool $retryable,
    ) {}

    public static function succeeded(int $responseStatus, int $durationMs): self
    {
        return new self(true, $responseStatus, null, $durationMs, false);
    }

    public static function failed(string $error, ?int $responseStatus = null, ?int $durationMs = null, bool $retryable = true): self
    {
        return new self(false, $responseStatus, $error, $durationMs, $retryable);
    }
}
