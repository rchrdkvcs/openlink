<?php

namespace Tests\Support;

use App\Services\Dns\DnsLookup;

class InMemoryDnsLookup implements DnsLookup
{
    private array $txt = [];

    private array $ips = [];

    private array $cnames = [];

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

    public function aliasTo(string $hostname, string $target): self
    {
        $this->cnames[$hostname] = [$target];

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

    public function cnameTargets(string $hostname): array
    {
        return $this->cnames[$hostname] ?? [];
    }
}
