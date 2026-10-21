<?php

namespace App\Services\DestinationHealth;

final readonly class DestinationTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public array $pinnedAddresses,
    ) {}

    public function curlResolve(): array
    {
        if ($this->pinnedAddresses === []) {
            return [];
        }

        $addresses = array_map(fn (string $address) => str_contains($address, ':') ? '['.$address.']' : $address, $this->pinnedAddresses);

        return [$this->host.':'.$this->port.':'.implode(',', $addresses)];
    }
}
