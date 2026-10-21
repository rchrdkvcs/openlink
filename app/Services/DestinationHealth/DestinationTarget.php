<?php

namespace App\Services\DestinationHealth;

final readonly class DestinationTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public ?string $pinnedAddress,
    ) {}

    public function curlResolve(): array
    {
        if ($this->pinnedAddress === null) {
            return [];
        }

        $address = str_contains($this->pinnedAddress, ':') ? '['.$this->pinnedAddress.']' : $this->pinnedAddress;

        return [$this->host.':'.$this->port.':'.$address];
    }
}
