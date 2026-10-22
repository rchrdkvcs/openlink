<?php

namespace App\Services\Webhooks;

use RuntimeException;

class UnsafeWebhookTarget extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
