<?php

namespace App\Services;

use App\Models\Domain;

class ApplicationHost
{
    public function host(): string
    {
        return $this->normalize(config('app.host') ?: config('app.url'));
    }

    public function isApplicationHost(?string $host): bool
    {
        return $this->normalize($host) === $this->host();
    }

    public function isApplicationDomain(Domain $domain): bool
    {
        return $this->isApplicationHost($domain->hostname);
    }

    private function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return 'localhost';
        }

        $host = parse_url(str_contains($value, '://') ? $value : 'http://'.$value, PHP_URL_HOST);

        return $host ? strtolower($host) : $value;
    }
}
