<?php

namespace App\Services\DomainEdge;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CloudflareZone
{
    private const API = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly Repository $cache,
        private readonly string $apiToken,
        private readonly string $configuredZoneId = '',
    ) {}

    public function id(): string
    {
        if ($this->configuredZoneId !== '') {
            return $this->configuredZoneId;
        }

        return $this->cache->remember($this->cacheKey('zone'), now()->addDay(), fn (): string => $this->discoverZoneId());
    }

    public function fallbackOrigin(): array
    {
        return $this->cache->remember($this->cacheKey('fallback-origin'), now()->addHour(), function (): array {
            $result = $this->client()
                ->get("zones/{$this->id()}/custom_hostnames/fallback_origin")
                ->throw()
                ->json('result');
            $origin = rtrim(strtolower(trim((string) data_get($result, 'origin'))), '.');

            if ($origin === '') {
                throw new CloudflareMisconfiguration('The Cloudflare zone has no Cloudflare for SaaS fallback origin. Set one under SSL/TLS → Custom Hostnames.');
            }

            return ['origin' => $origin, 'status' => (string) data_get($result, 'status')];
        });
    }

    public function tokenStatus(): string
    {
        return (string) $this->client()->get('user/tokens/verify')->throw()->json('result.status');
    }

    public function forget(): void
    {
        $this->cache->forget($this->cacheKey('zone'));
        $this->cache->forget($this->cacheKey('fallback-origin'));
    }

    public function customHostnames(?string $id = null): string
    {
        return "zones/{$this->id()}/custom_hostnames".($id === null ? '' : "/{$id}");
    }

    public function client(): PendingRequest
    {
        if ($this->apiToken === '') {
            throw new CloudflareMisconfiguration('OPENLINK_CLOUDFLARE_API_TOKEN is not set.');
        }

        return Http::baseUrl(self::API)
            ->withToken($this->apiToken)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10);
    }

    private function discoverZoneId(): string
    {
        $zones = $this->client()->get('zones', ['per_page' => 5])->throw()->json('result', []);

        if (count($zones) !== 1 || ! isset($zones[0]['id'])) {
            throw new CloudflareMisconfiguration(count($zones) === 0
                ? 'The Cloudflare API token cannot read any zone. Give it the "Zone: Read" permission on your zone.'
                : 'The Cloudflare API token can read several zones. Set OPENLINK_CLOUDFLARE_ZONE_ID to the zone that serves workspace domains.');
        }

        return (string) $zones[0]['id'];
    }

    private function cacheKey(string $name): string
    {
        return "openlink:cloudflare:{$name}:".hash('sha256', $this->apiToken.'|'.$this->configuredZoneId);
    }
}
