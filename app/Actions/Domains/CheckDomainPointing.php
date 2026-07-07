<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\DomainDnsTarget;

class CheckDomainPointing
{
    public function __construct(
        private readonly DnsResolver $resolver,
        private readonly DomainDnsTarget $target,
    ) {}

    public function handle(Domain $domain): Domain
    {
        $domainIps = array_map('strtolower', $this->resolver->ipAddresses($domain->hostname));
        $targetIps = $this->target->targetIps();
        $pointed = $domainIps !== [] && array_intersect($domainIps, $targetIps) !== [];

        if ($pointed) {
            $domain->forceFill(['dns_pointed_at' => now(), 'dns_check_error' => null])->save();

            if ($domain->status === Domain::STATUS_OWNERSHIP_VERIFIED) {
                $domain->activate();
            }

            return $domain;
        }

        // A proxy or CDN in front of the domain can hide the real target IPs,
        // so a mismatch is advisory: the domain still activates when real
        // traffic reaches this server (see ResolvePublicLink).
        if ($domain->status !== Domain::STATUS_ACTIVE) {
            $domain->forceFill([
                'dns_check_error' => $domainIps === []
                    ? 'The domain does not resolve to any IP address yet.'
                    : 'The domain resolves, but not to this server. If you use a proxy such as Cloudflare, this check may stay orange — visiting a short URL on this domain will activate it.',
            ])->save();
        }

        return $domain;
    }
}
