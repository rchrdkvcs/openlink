<?php

namespace Tests\Support;

use App\Services\Dns\DnsLookup;

class InMemoryDnsLookup implements DnsLookup
{
    private array $txt = [];

    private array $ips = [];

    public function publishTxt(string $hostname, string ...$values): self
    {
        $this->txt[$hostname] = $values;

        return $this;
    }

    public function pointTo(string $hostname, string ...$ips): self
    {
        $this->ips[$hostname] = $ips;

        return $this;
    }

    public function txtValues(string $hostname): array
    {
        return $this->txt[$hostname] ?? [];
    }

    public function ipAddresses(string $hostname): array
    {
        return $this->ips[$hostname] ?? [];
    }
}
