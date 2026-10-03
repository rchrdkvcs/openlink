<?php

namespace App\Services\Favicons;

class PublicUrl
{
    public function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? null;

        return in_array($scheme, ['http', 'https'], true) && $host && $this->isPublicHost($host);
    }

    public function origin(string $url): ?string
    {
        if (! $this->isAllowed($url)) {
            return null;
        }

        $parts = parse_url($url);
        $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }

    public function redirectTarget(string $current, ?string $location): ?string
    {
        if (! $location) {
            return null;
        }

        $location = $this->withSchemeAndHost($current, $location) ?? $location;

        return $this->isAllowed($location) ? $location : null;
    }

    public function absolute(string $baseUrl, string $url): ?string
    {
        if (str_starts_with($url, 'data:')) {
            return null;
        }

        $resolved = $this->withSchemeAndHost($baseUrl, $url);

        if ($resolved !== null) {
            return $resolved;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? null;
        $host = $base['host'] ?? null;

        if (! parse_url($url, PHP_URL_SCHEME) && $scheme && $host) {
            $directory = trim(str_replace('\\', '/', dirname($base['path'] ?? '/')), '/');

            return $scheme.'://'.$host.($directory ? '/'.$directory : '').'/'.$url;
        }

        return $url;
    }

    private function withSchemeAndHost(string $baseUrl, string $url): ?string
    {
        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? null;
        $host = $base['host'] ?? null;

        if (str_starts_with($url, '//') && $scheme) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/') && $scheme && $host) {
            return $scheme.'://'.$host.$url;
        }

        return null;
    }

    private function isPublicHost(string $host): bool
    {
        $host = trim(strtolower($host), '[]');

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);

        if (! $addresses) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
