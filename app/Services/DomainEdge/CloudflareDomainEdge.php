<?php

namespace App\Services\DomainEdge;

use App\Models\Domain;
use App\Services\Dns\DomainDnsTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class CloudflareDomainEdge implements DomainEdge
{
    private const API = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly DomainDnsTarget $target,
        private readonly string $zoneId,
        private readonly string $apiToken,
    ) {}

    public function connect(Domain $domain): EdgeConnection
    {
        if ($domain->is_default) {
            return EdgeConnection::connected();
        }

        if (! $domain->isOwnershipVerified()) {
            return EdgeConnection::waiting();
        }

        try {
            $hostname = $this->hostnameFor($domain);
        } catch (Throwable $exception) {
            report($exception);

            return EdgeConnection::waiting('Openlink could not reach Cloudflare to set up this domain. It will retry automatically.');
        }

        if (($hostname['status'] ?? null) === 'active' && data_get($hostname, 'ssl.status') === 'active') {
            return EdgeConnection::connected();
        }

        return EdgeConnection::waiting($this->waitingReason($domain, $hostname));
    }

    public function release(Domain $domain): void
    {
        if ($domain->edge_hostname_id === null) {
            return;
        }

        try {
            $response = $this->client()->delete($this->path($domain->edge_hostname_id));

            if (! $response->successful() && $response->status() !== 404) {
                $response->throw();
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $domain->edge_hostname_id = null;
    }

    private function hostnameFor(Domain $domain): array
    {
        if ($domain->edge_hostname_id !== null) {
            $response = $this->client()->get($this->path($domain->edge_hostname_id));

            if ($response->successful()) {
                return $response->json('result');
            }

            if (! $response->clientError()) {
                $response->throw();
            }
        }

        $hostname = $this->find($domain->hostname) ?? $this->create($domain->hostname);
        $domain->edge_hostname_id = $hostname['id'];

        return $hostname;
    }

    private function find(string $hostname): ?array
    {
        $matches = $this->client()
            ->get($this->path(), ['hostname' => $hostname])
            ->throw()
            ->json('result', []);

        return collect($matches)->first(fn (array $match): bool => strcasecmp($match['hostname'] ?? '', $hostname) === 0);
    }

    private function create(string $hostname): array
    {
        return $this->client()
            ->post($this->path(), [
                'hostname' => $hostname,
                'ssl' => [
                    'method' => 'http',
                    'type' => 'dv',
                    'settings' => ['min_tls_version' => '1.2'],
                ],
            ])
            ->throw()
            ->json('result');
    }

    private function waitingReason(Domain $domain, array $hostname): string
    {
        $reported = data_get($hostname, 'ssl.validation_errors.0.message')
            ?? data_get($hostname, 'verification_errors.0');

        if (is_string($reported) && $reported !== '') {
            return $reported;
        }

        return "Point {$domain->hostname} to {$this->target->value()} with a CNAME record. HTTPS is issued automatically once it resolves.";
    }

    private function path(?string $id = null): string
    {
        return "zones/{$this->zoneId}/custom_hostnames".($id === null ? '' : "/{$id}");
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::API)
            ->withToken($this->apiToken)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10);
    }
}
