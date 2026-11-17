<?php

namespace App\Services\DomainEdge;

use App\Models\Domain;
use App\Services\Dns\DnsLookup;
use App\Services\Dns\DomainDnsTarget;
use Illuminate\Http\Client\RequestException;
use Throwable;

class CloudflareDomainEdge implements DomainEdge
{
    public function __construct(
        private readonly CloudflareZone $zone,
        private readonly DnsLookup $dns,
        private readonly DomainDnsTarget $target,
    ) {}

    public function connect(Domain $domain): EdgeConnection
    {
        if ($domain->is_default) {
            return EdgeConnection::connected();
        }

        $aliased = $this->aliasesTarget($domain->hostname);

        if (! $domain->isOwnershipVerified() && ! $aliased) {
            return EdgeConnection::waiting('HTTPS setup starts as soon as Openlink finds the TXT or CNAME record.');
        }

        try {
            $hostname = $this->hostnameFor($domain);
        } catch (Throwable $exception) {
            report($exception);

            return EdgeConnection::waiting($this->failureReason($exception), $domain->edge_hostname_id);
        }

        $id = (string) $hostname['id'];

        if (($hostname['status'] ?? null) === 'active' && data_get($hostname, 'ssl.status') === 'active') {
            return EdgeConnection::connected($id);
        }

        $problem = $this->reportedProblem($domain, $hostname);

        if ($aliased || ($hostname['status'] ?? null) === 'active') {
            return EdgeConnection::issuing($id, $problem);
        }

        return EdgeConnection::waiting(
            $problem ?? "Point {$domain->hostname} to {$this->target->value()} with a CNAME record. HTTPS is issued automatically once it resolves.",
            $id,
        );
    }

    public function release(string $hostnameId): void
    {
        $response = $this->zone->client()->delete($this->zone->customHostnames($hostnameId));

        if ($response->failed() && $response->status() !== 404) {
            throw new RequestException($response);
        }
    }

    private function aliasesTarget(string $hostname): bool
    {
        $aliases = $this->dns->cnameTargets($hostname);

        return $aliases !== [] && in_array(rtrim(strtolower($this->target->value()), '.'), $aliases, true);
    }

    private function hostnameFor(Domain $domain): array
    {
        if ($domain->edge_hostname_id !== null) {
            $response = $this->zone->client()->get($this->zone->customHostnames($domain->edge_hostname_id));

            if ($response->successful()) {
                return $response->json('result');
            }

            if ($response->status() !== 404) {
                throw new RequestException($response);
            }
        }

        return $this->find($domain->hostname) ?? $this->create($domain->hostname);
    }

    private function find(string $hostname): ?array
    {
        $matches = $this->zone->client()
            ->get($this->zone->customHostnames(), ['hostname' => $hostname])
            ->throw()
            ->json('result', []);

        return collect($matches)->first(fn (array $match): bool => strcasecmp($match['hostname'] ?? '', $hostname) === 0);
    }

    private function create(string $hostname): array
    {
        return $this->zone->client()
            ->post($this->zone->customHostnames(), [
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

    private function failureReason(Throwable $exception): string
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return match (true) {
            $exception instanceof CloudflareMisconfiguration, $status === 401, $status === 403 => 'HTTPS setup is paused because this Openlink instance cannot use Cloudflare right now. Your DNS records are not the problem; the instance administrator has to fix the Cloudflare settings.',
            $status === 429 => 'Cloudflare is limiting requests right now. Openlink will retry automatically.',
            default => 'Openlink could not reach Cloudflare to set up this domain. It will retry automatically.',
        };
    }

    private function reportedProblem(Domain $domain, array $hostname): ?string
    {
        $messages = collect(data_get($hostname, 'ssl.validation_errors', []))
            ->map(fn (mixed $error): mixed => is_array($error) ? ($error['message'] ?? null) : $error)
            ->merge(data_get($hostname, 'verification_errors', []))
            ->filter(fn (mixed $message): bool => is_string($message) && trim($message) !== '');

        if ($messages->isEmpty()) {
            return null;
        }

        $reported = strtolower($messages->implode(' '));
        $host = $domain->hostname;
        $target = $this->target->value();

        return match (true) {
            str_contains($reported, 'caa') => "A CAA record on {$host} blocks the certificate authorities Cloudflare uses. Remove it, or also allow letsencrypt.org and pki.goog.",
            str_contains($reported, 'cname'),
            str_contains($reported, 'not point'),
            str_contains($reported, 'nxdomain'),
            str_contains($reported, 'not resolve') => "{$host} does not point to {$target} yet. Check the CNAME record; DNS changes can take a while to spread.",
            str_contains($reported, 'timed out'),
            str_contains($reported, 'timeout') => "Certificate validation for {$host} timed out. Check that its CNAME record points to {$target}. Openlink keeps retrying.",
            str_contains($reported, 'pending'),
            str_contains($reported, 'validation') => "Cloudflare is validating {$host} before issuing HTTPS. This usually takes a few minutes.",
            default => "Cloudflare could not issue HTTPS for {$host} yet. Openlink keeps retrying.",
        };
    }
}
