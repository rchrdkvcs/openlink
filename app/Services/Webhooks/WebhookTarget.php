<?php

namespace App\Services\Webhooks;

final readonly class WebhookTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $address,
    ) {}

    public function curlResolve(): string
    {
        $address = str_contains($this->address, ':') ? '['.$this->address.']' : $this->address;

        return $this->host.':'.$this->port.':'.$address;
    }
}
