<?php

namespace App\Services\Dns;

use App\Services\InstanceSettings;

class DomainDnsTarget
{
    public function __construct(
        private readonly InstanceSettings $settings,
        private readonly DnsResolver $resolver,
    ) {}

    public function value(): string
    {
        $target = trim((string) $this->settings->get('dns_target', ''));

        return $target !== '' ? $target : (string) $this->settings->get('default_domain');
    }

    public function recordType(): string
    {
        return filter_var($this->value(), FILTER_VALIDATE_IP) ? 'A' : 'CNAME';
    }

    /** @return array<int, string> */
    public function targetIps(): array
    {
        $value = $this->value();

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return [strtolower($value)];
        }

        return array_map('strtolower', $this->resolver->ipAddresses($value));
    }
}
