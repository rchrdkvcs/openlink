<?php

namespace App\Services\DomainEdge;

use App\Models\Domain;
use App\Services\Dns\CloudflareProxyRanges;
use App\Services\Dns\DnsLookup;
use App\Services\Dns\DomainDnsTarget;

class DirectDomainEdge implements DomainEdge
{
    public function __construct(
        private readonly DnsLookup $dns,
        private readonly DomainDnsTarget $target,
        private readonly CloudflareProxyRanges $cloudflare,
    ) {}

    public function connect(Domain $domain): EdgeConnection
    {
        $ips = array_map('strtolower', $this->dns->ipAddresses($domain->hostname));
        $pointed = $ips !== [] && array_intersect($ips, $this->target->targetIps()) !== [];
        $proxied = $domain->isOwnershipVerified() && $this->cloudflare->coverAll($ips);

        if ($pointed || $proxied) {
            return EdgeConnection::connected();
        }

        return EdgeConnection::waiting($ips === []
            ? 'The domain does not resolve to any IP address yet.'
            : 'The domain resolves, but not to this server. If you use a proxy such as Cloudflare, this check may stay orange — visiting a short URL on this domain will activate it.');
    }

    public function release(Domain $domain): void {}
}
