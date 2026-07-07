<?php

namespace App\Actions\Domains;

use App\Models\Domain;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\DomainDnsTarget;

class CheckDomainPointing
{
    private const CLOUDFLARE_PROXY_CIDRS = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public function __construct(
        private readonly DnsResolver $resolver,
        private readonly DomainDnsTarget $target,
    ) {}

    public function handle(Domain $domain): Domain
    {
        $domainIps = array_map('strtolower', $this->resolver->ipAddresses($domain->hostname));
        $targetIps = $this->target->targetIps();
        $pointed = $domainIps !== [] && array_intersect($domainIps, $targetIps) !== [];
        $proxiedByCloudflare = $domain->isOwnershipVerified() && $this->resolvesOnlyToCloudflareProxy($domainIps);

        if ($pointed || $proxiedByCloudflare) {
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

    /** @param array<int, string> $ips */
    private function resolvesOnlyToCloudflareProxy(array $ips): bool
    {
        return $ips !== [] && collect($ips)->every(
            fn (string $ip): bool => $this->matchesAnyCidr($ip, self::CLOUDFLARE_PROXY_CIDRS)
        );
    }

    /** @param array<int, string> $cidrs */
    private function matchesAnyCidr(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->matchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);

        $ipBytes = @inet_pton($ip);
        $networkBytes = @inet_pton($network);

        if ($ipBytes === false || $networkBytes === false || strlen($ipBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefix = (int) $prefix;
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainingBits)) & 0xFF);

        return ($ipBytes[$fullBytes] & $mask) === ($networkBytes[$fullBytes] & $mask);
    }
}
