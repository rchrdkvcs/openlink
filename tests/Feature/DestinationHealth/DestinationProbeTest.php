<?php

namespace Tests\Feature\DestinationHealth;

use App\Enums\DestinationHealthStatus;
use App\Services\DestinationHealth\DestinationProbe;
use App\Services\Dns\DnsLookup;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class DestinationProbeTest extends TestCase
{
    private InMemoryDnsLookup $dns;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new InMemoryDnsLookup;
        $this->app->instance(DnsLookup::class, $this->dns);
    }

    public function test_reachable_destination_is_checked_with_head_pinned_to_the_validated_address(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn () => Http::response('', 200));

        $result = $this->probe()->check('https://example.com/landing?ref=1#top');

        $this->assertSame(DestinationHealthStatus::Reachable, $result->status);
        $this->assertSame(200, $result->httpStatus);
        $this->assertCount(1, $this->sent);
        $this->assertSame('HEAD', $this->sent[0]['method']);
        $this->assertSame('https://example.com/landing?ref=1', $this->sent[0]['url']);
        $this->assertSame(['example.com:443:93.184.216.34'], $this->sent[0]['options']['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($this->sent[0]['options']['allow_redirects']);
    }

    public function test_requests_carry_no_cookies_credentials_or_authorization(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn () => Http::response('', 200));

        $this->probe()->check('https://visitor:secret@example.com/private');

        $request = $this->sent[0];
        $this->assertSame('https://example.com/private', $request['url']);
        $this->assertFalse($request['request']->hasHeader('Authorization'));
        $this->assertFalse($request['request']->hasHeader('Cookie'));
        $this->assertStringContainsString('Openlink', $request['request']->header('User-Agent')[0]);
        $this->assertFalse($request['options']['cookies'] ?? false);
    }

    public function test_unsupported_head_falls_back_to_get(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn (Request $request) => Http::response('', $request->method() === 'HEAD' ? 405 : 200));

        $result = $this->probe()->check('https://example.com/');

        $this->assertSame(DestinationHealthStatus::Reachable, $result->status);
        $this->assertSame(['HEAD', 'GET'], array_column($this->sent, 'method'));
        $this->assertIsCallable($this->sent[1]['options']['on_headers']);
    }

    public function test_missing_pages_and_server_errors_are_http_failures(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn (Request $request) => Http::response('', str_contains($request->url(), 'gone') ? 410 : (str_contains($request->url(), 'down') ? 503 : 404)));

        foreach (['missing' => 404, 'gone' => 410, 'down' => 503] as $path => $status) {
            $result = $this->probe()->check("https://example.com/$path");

            $this->assertSame(DestinationHealthStatus::HttpFailure, $result->status, $path);
            $this->assertSame($status, $result->httpStatus);
        }
    }

    public function test_bot_rejections_and_challenges_are_inconclusive_not_failures(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn (Request $request) => match (true) {
            str_contains($request->url(), 'forbidden') => Http::response('', 403),
            str_contains($request->url(), 'limited') => Http::response('', 429),
            str_contains($request->url(), 'login') => Http::response('', 401),
            default => Http::response('', 503, ['cf-mitigated' => 'challenge']),
        });

        foreach (['forbidden' => 403, 'limited' => 429, 'login' => 401, 'challenge' => 503] as $path => $status) {
            $result = $this->probe()->check("https://example.com/$path");

            $this->assertSame(DestinationHealthStatus::Inconclusive, $result->status, $path);
            $this->assertSame($status, $result->httpStatus);
            $this->assertFalse($result->status->isFailure());
        }
    }

    public function test_hosts_resolving_to_non_public_addresses_are_blocked_without_a_request(): void
    {
        $this->dns->pointTo('internal.example.com', '10.0.0.5');
        $this->dns->pointTo('mixed.example.com', '93.184.216.34', '127.0.0.1');
        $this->dns->pointTo('metadata.example.com', '169.254.169.254');
        $this->dns->pointTo('v6.example.com', '::ffff:127.0.0.1');
        $this->respond(fn () => Http::response('', 200));

        foreach (['internal', 'mixed', 'metadata', 'v6'] as $host) {
            $result = $this->probe()->check("https://$host.example.com/");

            $this->assertSame(DestinationHealthStatus::Blocked, $result->status, $host);
        }

        $this->assertSame([], $this->sent);
    }

    public function test_literal_and_alternate_address_forms_are_blocked_without_a_request(): void
    {
        $this->respond(fn () => Http::response('', 200));

        foreach ([
            'http://127.0.0.1/',
            'http://[::1]/',
            'http://[::ffff:169.254.169.254]/',
            'http://2130706433/',
            'http://0x7f000001/',
            'http://0177.0.0.1/',
            'http://127.1/',
            'http://localhost/',
            'http://printer.local/',
            'http://metadata.google.internal/',
            'ftp://93.184.216.34/',
            'not a url',
        ] as $url) {
            $this->assertSame(DestinationHealthStatus::Blocked, $this->probe()->check($url)->status, $url);
        }

        $this->assertSame([], $this->sent);
    }

    public function test_every_redirect_hop_is_revalidated_and_pinned(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->dns->pointTo('cdn.example.net', '93.184.216.35');
        $this->respond(fn (Request $request) => match ($request->url()) {
            'https://example.com/start' => Http::response('', 301, ['Location' => '/next']),
            'https://example.com/next' => Http::response('', 302, ['Location' => 'https://cdn.example.net/final']),
            default => Http::response('', 200),
        });

        $result = $this->probe()->check('https://example.com/start');

        $this->assertSame(DestinationHealthStatus::Reachable, $result->status);
        $this->assertSame(
            ['https://example.com/start', 'https://example.com/next', 'https://cdn.example.net/final'],
            array_column($this->sent, 'url'),
        );
        $this->assertSame(['cdn.example.net:443:93.184.216.35'], $this->sent[2]['options']['curl'][CURLOPT_RESOLVE]);
    }

    public function test_redirects_to_non_public_targets_are_blocked_before_connecting(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->dns->pointTo('rebind.example.com', '192.168.1.1');
        $this->respond(fn (Request $request) => match ($request->url()) {
            'https://example.com/private' => Http::response('', 302, ['Location' => 'https://rebind.example.com/']),
            default => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        });

        $this->assertSame(DestinationHealthStatus::Blocked, $this->probe()->check('https://example.com/private')->status);
        $this->assertSame(DestinationHealthStatus::Blocked, $this->probe()->check('https://example.com/metadata')->status);
        $this->assertSame(['https://example.com/private', 'https://example.com/metadata'], array_column($this->sent, 'url'));
    }

    public function test_redirect_chains_are_bounded(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn () => Http::response('', 302, ['Location' => '/loop']));

        $result = $this->probe()->check('https://example.com/loop');

        $this->assertSame(DestinationHealthStatus::HttpFailure, $result->status);
        $this->assertSame(DestinationProbe::MAX_REDIRECTS + 1, count($this->sent));
    }

    public function test_dns_tls_connection_and_timeout_failures_are_distinguished(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn (Request $request) => Http::failedConnection(match (true) {
            str_contains($request->url(), 'slow') => 'cURL error 28: Operation timed out after 5000 milliseconds',
            str_contains($request->url(), 'tls') => 'cURL error 60: SSL certificate problem: certificate has expired',
            default => 'cURL error 7: Failed to connect to example.com port 443: Connection refused',
        })($request));

        $this->assertSame(DestinationHealthStatus::DnsFailure, $this->probe()->check('https://unknown.example.org/')->status);
        $this->assertSame(DestinationHealthStatus::Timeout, $this->probe()->check('https://example.com/slow')->status);
        $this->assertSame(DestinationHealthStatus::TlsFailure, $this->probe()->check('https://example.com/tls')->status);
        $this->assertSame(DestinationHealthStatus::ConnectionFailure, $this->probe()->check('https://example.com/refused')->status);
    }

    public function test_requests_are_bounded_in_duration(): void
    {
        $this->dns->pointTo('example.com', '93.184.216.34');
        $this->respond(fn () => Http::response('', 200));

        $this->probe()->check('https://example.com/');

        $this->assertLessThanOrEqual(10, $this->sent[0]['options']['timeout']);
        $this->assertLessThanOrEqual(5, $this->sent[0]['options']['connect_timeout']);
    }

    private function probe(): DestinationProbe
    {
        return $this->app->make(DestinationProbe::class);
    }

    private function respond(callable $responder): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request, array $options) use ($responder) {
            $this->sent[] = ['method' => $request->method(), 'url' => $request->url(), 'options' => $options, 'request' => $request];

            return $responder($request);
        });
    }
}
