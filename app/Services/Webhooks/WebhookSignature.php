<?php

namespace App\Services\Webhooks;

class WebhookSignature
{
    public const VERSION = 'v1';

    public function sign(string $secret, int $timestamp, string $body): string
    {
        return self::VERSION.'='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
