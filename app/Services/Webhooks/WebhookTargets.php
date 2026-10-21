<?php

namespace App\Services\Webhooks;

use App\Services\Dns\DnsLookup;

class WebhookTargets
{
    public function __construct(
        private readonly DnsLookup $dns,
        private readonly PublicAddress $addresses,
    ) {}

    public function resolve(string $url): WebhookTarget
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || blank($parts['host'] ?? null)) {
            throw new UnsafeWebhookTarget('The endpoint URL must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeWebhookTarget('The endpoint URL must not contain credentials.');
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($this->isInternalName($host)) {
            throw new UnsafeWebhookTarget('The endpoint resolves to a private or reserved address.');
        }

        $isAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if (! $isAddress && ! $this->isHostname($host)) {
            throw new UnsafeWebhookTarget('The endpoint hostname is not valid.');
        }

        $addresses = $isAddress ? [$host] : $this->dns->ipAddresses($host);

        if ($addresses === []) {
            throw new UnsafeWebhookTarget('The endpoint hostname does not resolve.');
        }

        foreach ($addresses as $address) {
            if (! $this->addresses->isPublic($address)) {
                throw new UnsafeWebhookTarget('The endpoint resolves to a private or reserved address.');
            }
        }

        return new WebhookTarget($url, $host, (int) ($parts['port'] ?? 443), $addresses[0]);
    }

    private function isHostname(string $host): bool
    {
        $labels = explode('.', rtrim($host, '.'));
        $topLevel = end($labels);

        return preg_match('/^[a-z0-9.-]+$/', $host) === 1
            && preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', $topLevel) !== 1;
    }

    private function isInternalName(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal');
    }
}
