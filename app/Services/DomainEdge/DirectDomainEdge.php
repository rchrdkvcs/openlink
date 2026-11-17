<?php

namespace App\Services\DomainEdge;

use App\Models\Domain;
use App\Services\Dns\DnsLookup;
use App\Services\Dns\DomainDnsTarget;

class DirectDomainEdge implements DomainEdge
{
    public function __construct(
        private readonly DnsLookup $dns,
        private readonly DomainDnsTarget $target,
    ) {}

    public function connect(Domain $domain): EdgeConnection
    {
        $ips = array_map('strtolower', $this->dns->ipAddresses($domain->hostname));

        if ($ips !== [] && array_intersect($ips, $this->target->targetIps()) !== []) {
            return EdgeConnection::connected();
        }

        return EdgeConnection::waiting($ips === []
            ? 'The domain does not resolve to any IP address yet.'
            : 'The domain resolves, but not to this server.');
    }

    public function release(Domain $domain): void {}
}
