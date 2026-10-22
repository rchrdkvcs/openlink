<?php

namespace App\Services\DestinationHealth;

use App\Enums\DestinationHealthStatus;
use App\Services\Dns\DnsLookup;
use Carbon\CarbonInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class DestinationProbe
{
    public const MAX_REDIRECTS = 5;

    public const USER_AGENT = 'Mozilla/5.0 (compatible; OpenlinkDestinationCheck/1.0)';

    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 6;

    public const DEADLINE = 30;

    private const BLOCKED_HOST_SUFFIXES = ['.localhost', '.local', '.internal', '.home.arpa'];

    private const TLS_ERRORS = [35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91];

    public function __construct(
        private readonly DnsLookup $dns,
        private readonly PublicAddress $addresses,
    ) {}

    public function check(string $url): DestinationProbeResult
    {
        $current = $url;
        $deadline = now()->addSeconds(self::DEADLINE);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = $this->target($current, $hop > 0);

            if ($target instanceof DestinationProbeResult) {
                return $target;
            }

            $response = $this->request('HEAD', $target, $deadline);

            if ($this->shouldRetryWithGet($response)) {
                $response = $this->request('GET', $target, $deadline);
            }

            if ($response instanceof DestinationProbeResult) {
                return $response;
            }

            if (! $this->isRedirect($response)) {
                return $this->classify($response);
            }

            $current = $this->redirectTarget($current, $response->header('Location'));

            if ($current === null) {
                return new DestinationProbeResult(DestinationHealthStatus::HttpFailure, $response->status(), 'The destination sent an invalid redirect.');
            }
        }

        return new DestinationProbeResult(DestinationHealthStatus::HttpFailure, null, 'The destination redirects too many times.');
    }

    private function target(string $url, bool $redirected): DestinationTarget|DestinationProbeResult
    {
        $blocked = fn (string $reason) => new DestinationProbeResult(
            DestinationHealthStatus::Blocked,
            null,
            $redirected ? "The destination redirects to $reason." : "The destination is $reason.",
        );

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = rtrim(strtolower((string) ($parts['host'] ?? '')), '.');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return $blocked('not a web address');
        }

        if (! preg_match('/^[\x20-\x7e]*$/', $host)) {
            $host = function_exists('idn_to_ascii') ? (idn_to_ascii($host) ?: '') : '';

            if ($host === '') {
                return $blocked('not a supported web address');
            }
        }

        $literal = $this->addresses->literal($host);

        if ($host === '' || $host === 'localhost' || $this->hasBlockedSuffix($host) || $this->addresses->isAmbiguousNumericHost($host)) {
            return $blocked('a private address');
        }

        try {
            $resolved = $literal !== null ? [$literal] : $this->dns->ipAddresses($host);
        } catch (Throwable) {
            $resolved = [];
        }

        if ($resolved === []) {
            return new DestinationProbeResult(DestinationHealthStatus::DnsFailure, null, 'The destination host does not resolve.');
        }

        foreach ($resolved as $address) {
            if (! $this->addresses->isPublic($address)) {
                return $blocked('a private address');
            }
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $authority = $literal !== null && str_contains($literal, ':') ? '['.$literal.']' : $host;
        $requestUrl = $scheme.'://'.$authority.(isset($parts['port']) ? ':'.$port : '')
            .($parts['path'] ?? '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');

        return new DestinationTarget($requestUrl, $host, $port, $literal === null ? $resolved : []);
    }

    private function request(string $method, DestinationTarget $target, CarbonInterface $deadline): Response|DestinationProbeResult
    {
        $remaining = (int) floor(now()->diffInSeconds($deadline, false));

        if ($remaining < 1) {
            return new DestinationProbeResult(DestinationHealthStatus::Timeout, null, 'The destination took too long to respond.');
        }

        $received = null;

        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'cookies' => false,
                'decode_content' => false,
                'proxy' => '',
                'protocols' => ['http', 'https'],
                'curl' => [CURLOPT_RESOLVE => $target->curlResolve()],
                'on_headers' => function (ResponseInterface $response) use (&$received): void {
                    $received = $response;

                    throw new RuntimeException('Response headers received.');
                },
            ])
                ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => '*/*'])
                ->connectTimeout(min(self::CONNECT_TIMEOUT, $remaining))
                ->timeout(min(self::TIMEOUT, $remaining))
                ->withoutRedirecting()
                ->send($method, $target->url);
        } catch (Throwable $exception) {
            if ($received !== null) {
                return new Response($received);
            }

            if ($exception instanceof ConnectionException) {
                return $this->transportFailure($exception);
            }

            return new DestinationProbeResult(DestinationHealthStatus::ConnectionFailure, null, 'The destination could not be reached.');
        }
    }

    private function shouldRetryWithGet(Response|DestinationProbeResult $response): bool
    {
        if ($response instanceof DestinationProbeResult) {
            return $response->status === DestinationHealthStatus::ConnectionFailure;
        }

        return ! $this->isRedirect($response) && ! $response->successful();
    }

    private function classify(Response $response): DestinationProbeResult
    {
        $status = $response->status();

        return match (true) {
            $status >= 200 && $status < 400 => new DestinationProbeResult(DestinationHealthStatus::Reachable, $status),
            strtolower($response->header('cf-mitigated')) === 'challenge' => new DestinationProbeResult(DestinationHealthStatus::Inconclusive, $status, 'The destination asked for a browser challenge.'),
            in_array($status, [404, 410], true) => new DestinationProbeResult(DestinationHealthStatus::HttpFailure, $status, 'The destination page was not found.'),
            $status >= 500 => new DestinationProbeResult(DestinationHealthStatus::HttpFailure, $status, 'The destination server returned an error.'),
            default => new DestinationProbeResult(DestinationHealthStatus::Inconclusive, $status, 'The destination refused automated checks.'),
        };
    }

    private function transportFailure(ConnectionException $exception): DestinationProbeResult
    {
        $code = preg_match('/cURL error (\d+)/', $exception->getMessage(), $matches) ? (int) $matches[1] : null;

        return match (true) {
            $code === 6 => new DestinationProbeResult(DestinationHealthStatus::DnsFailure, null, 'The destination host does not resolve.'),
            $code === 28 => new DestinationProbeResult(DestinationHealthStatus::Timeout, null, 'The destination took too long to respond.'),
            in_array($code, self::TLS_ERRORS, true) => new DestinationProbeResult(DestinationHealthStatus::TlsFailure, null, 'The destination has a TLS or certificate problem.'),
            default => new DestinationProbeResult(DestinationHealthStatus::ConnectionFailure, null, 'The destination refused or dropped the connection.'),
        };
    }

    private function isRedirect(Response $response): bool
    {
        return $response->status() >= 300 && $response->status() < 400 && $response->header('Location') !== '';
    }

    private function redirectTarget(string $current, string $location): ?string
    {
        try {
            return (string) UriResolver::resolve(new Uri($current), new Uri($location))->withFragment('');
        } catch (Throwable) {
            return null;
        }
    }

    private function hasBlockedSuffix(string $host): bool
    {
        foreach (self::BLOCKED_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
