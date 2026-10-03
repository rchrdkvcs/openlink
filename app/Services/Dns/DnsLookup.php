<?php

namespace App\Services\Dns;

interface DnsLookup
{
    public function txtValues(string $hostname): array;

    public function ipAddresses(string $hostname): array;
}
