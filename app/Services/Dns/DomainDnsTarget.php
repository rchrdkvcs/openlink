<?php

namespace App\Services\Dns;

use App\Enums\DomainEdgeMode;
use App\Services\DomainEdge\CloudflareZone;
use App\Services\InstanceSettings;
use Throwable;

class DomainDnsTarget
{
    private ?string $resolved = null;

    public function __construct(
        private readonly InstanceSettings $settings,
        private readonly DnsLookup $resolver,
        private readonly DomainEdgeMode $mode,
        private readonly CloudflareZone $zone,
    ) {}

    public function value(): string
    {
        return $this->resolved ??= $this->isManagedByEdge() ? $this->edgeValue() : $this->configuredValue();
    }

    public function isManagedByEdge(): bool
    {
        return $this->mode === DomainEdgeMode::Cloudflare;
    }

    public function recordType(): string
    {
        return filter_var($this->value(), FILTER_VALIDATE_IP) ? 'A' : 'CNAME';
    }

    public function targetIps(): array
    {
        $value = $this->value();

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return [strtolower($value)];
        }

        return array_map('strtolower', $this->resolver->ipAddresses($value));
    }

    private function edgeValue(): string
    {
        try {
            return $this->zone->fallbackOrigin()['origin'];
        } catch (Throwable $exception) {
            report($exception);

            return $this->configuredValue();
        }
    }

    private function configuredValue(): string
    {
        $target = trim((string) $this->settings->get('dns_target', ''));

        return $target !== '' ? $target : (string) $this->settings->get('default_domain');
    }
}
