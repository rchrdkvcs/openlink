<?php

namespace App\Actions\Domains;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Workspace;
use App\Services\Dns\CloudflareProxyRanges;
use App\Services\Dns\DnsLookup;
use App\Services\Dns\DomainDnsTarget;
use App\Services\InstanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomainLifecycle
{
    public function __construct(
        private readonly DnsLookup $dns,
        private readonly DomainDnsTarget $target,
        private readonly CloudflareProxyRanges $cloudflare,
        private readonly InstanceSettings $settings,
    ) {}

    public function register(Workspace $workspace, string $hostname): Domain
    {
        return Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => Domain::normalizeHostname($hostname),
            'status' => DomainStatus::Pending,
            'verification_token' => Str::random(40),
        ]);
    }

    public function check(Domain $domain): Domain
    {
        if ($domain->isDisabled()) {
            return $domain;
        }

        $this->checkOwnership($domain);
        $this->checkPointing($domain);
        $domain->save();

        return $domain;
    }

    public function observeTraffic(Domain $domain, string $host): void
    {
        if ($domain->status === DomainStatus::OwnershipVerified
            && ! $domain->isDisabled()
            && strcasecmp($host, $domain->hostname) === 0) {
            $this->activate($domain);
            $domain->save();
        }
    }

    public function activateOnObservedTraffic(Request $request, Domain $domain): void
    {
        $this->observeTraffic($domain, $request->getHost());
    }

    public function disable(Domain $domain): Domain
    {
        $domain->forceFill([
            'status' => DomainStatus::Disabled,
            'disabled_at' => $domain->disabled_at ?? now(),
        ])->save();

        return $domain;
    }

    public function ensureDefaultDomain(string $hostname): Domain
    {
        $hostname = Domain::normalizeHostname($hostname);

        Domain::query()
            ->where('is_default', true)
            ->where('hostname', '!=', $hostname)
            ->update(['is_default' => false]);

        $domain = Domain::query()->firstOrNew(['hostname' => $hostname]);
        $domain->forceFill([
            'workspace_id' => null,
            'status' => DomainStatus::Active,
            'verification_token' => $domain->verification_token ?? Str::random(40),
            'is_default' => true,
            'verified_at' => $domain->verified_at ?? now(),
            'dns_pointed_at' => $domain->dns_pointed_at ?? now(),
            'disabled_at' => null,
        ])->save();

        $this->settings->set('default_domain', $hostname);

        return $domain;
    }

    private function checkOwnership(Domain $domain): void
    {
        $found = in_array($domain->verificationTxtValue(), $this->dns->txtValues($domain->verificationTxtName()), true);
        $active = $domain->status === DomainStatus::Active;

        $domain->forceFill([
            'status' => match (true) {
                $active => DomainStatus::Active,
                $found => DomainStatus::OwnershipVerified,
                default => DomainStatus::Failed,
            },
            'verified_at' => $found ? ($domain->verified_at ?? now()) : $domain->verified_at,
            'last_checked_at' => now(),
            'failure_reason' => $found || $active ? null : 'Expected DNS TXT record was not found.',
        ]);
    }

    private function checkPointing(Domain $domain): void
    {
        $ips = array_map('strtolower', $this->dns->ipAddresses($domain->hostname));
        $pointed = $ips !== [] && array_intersect($ips, $this->target->targetIps()) !== [];
        $proxied = $domain->isOwnershipVerified() && $this->cloudflare->coverAll($ips);

        if ($pointed || $proxied) {
            $domain->forceFill(['dns_pointed_at' => now(), 'dns_check_error' => null]);

            if ($domain->status === DomainStatus::OwnershipVerified) {
                $this->activate($domain);
            }

            return;
        }

        if ($domain->status !== DomainStatus::Active) {
            $domain->dns_check_error = $ips === []
                ? 'The domain does not resolve to any IP address yet.'
                : 'The domain resolves, but not to this server. If you use a proxy such as Cloudflare, this check may stay orange — visiting a short URL on this domain will activate it.';
        }
    }

    private function activate(Domain $domain): void
    {
        $domain->forceFill([
            'status' => DomainStatus::Active,
            'dns_pointed_at' => $domain->dns_pointed_at ?? now(),
            'dns_check_error' => null,
        ]);
    }
}
