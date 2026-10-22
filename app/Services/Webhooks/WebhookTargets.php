<?php

namespace App\Services\Webhooks;

use App\Services\Dns\DnsLookup;

class WebhookTargets
{
    public function __construct(
        private readonly DnsLookup $dns,
        private readonly PublicAddress $addresses,
    ) {}

    public function assertAcceptable(string $url): void
    {
        $this->inspect($url);
    }

    public function resolve(string $url): WebhookTarget
    {
        [$host, $name, $port, $address] = $this->inspect($url);
        $addresses = $address !== null ? [$address] : $this->dns->ipAddresses($name);

        if ($addresses === []) {
            throw new UnsafeWebhookTarget('The endpoint hostname does not resolve.', retryable: true);
        }

        foreach ($addresses as $candidate) {
            $this->assertPublic($candidate);
        }

        return new WebhookTarget($url, $host, $port, $addresses[0]);
    }

    private function inspect(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || blank($parts['host'] ?? null)) {
            throw new UnsafeWebhookTarget('The endpoint URL must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeWebhookTarget('The endpoint URL must not contain credentials.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $name = rtrim($host, '.');
        $port = (int) ($parts['port'] ?? 443);

        if (filter_var($name, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublic($name);

            return [$host, $name, $port, $name];
        }

        if (! $this->isHostname($name)) {
            throw new UnsafeWebhookTarget('The endpoint hostname is not valid.');
        }

        if ($this->isInternalName($name)) {
            throw new UnsafeWebhookTarget('The endpoint resolves to a private or reserved address.');
        }

        return [$host, $name, $port, null];
    }

    private function assertPublic(string $address): void
    {
        if (! $this->addresses->isPublic($address)) {
            throw new UnsafeWebhookTarget('The endpoint resolves to a private or reserved address.');
        }
    }

    private function isHostname(string $name): bool
    {
        $labels = explode('.', $name);

        return preg_match('/^[a-z0-9.-]+$/', $name) === 1
            && ! in_array('', $labels, true)
            && preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', end($labels)) !== 1;
    }

    private function isInternalName(string $name): bool
    {
        return $name === 'localhost'
            || str_ends_with($name, '.localhost')
            || str_ends_with($name, '.local')
            || str_ends_with($name, '.internal');
    }
}
