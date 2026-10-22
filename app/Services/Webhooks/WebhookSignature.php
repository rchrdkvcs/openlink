<?php

namespace App\Services\Webhooks;

class WebhookSignature
{
    public const VERSION = 'v1';

    public function sign(string $secret, int $timestamp, string $body): string
    {
        return self::VERSION.'='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function header(array $secrets, int $timestamp, string $body): string
    {
        return implode(',', array_map(fn (string $secret) => $this->sign($secret, $timestamp, $body), $secrets));
    }
}
