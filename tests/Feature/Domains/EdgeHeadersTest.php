<?php

namespace Tests\Feature\Domains;

use App\Enums\DomainStatus;
use App\Models\AnalyticsEvent;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\TestCase;

class EdgeHeadersTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    private ShortLink $link;

    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['openlink.domain_edge.secrets' => ['previous-secret', 'edge-secret']]);

        [$workspace, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            $this->seen = [
                'host' => $event->request->getHost(),
                'ip' => $event->request->ip(),
                'secure' => $event->request->isSecure(),
                'url' => url('/probe'),
                'headers' => array_keys($event->request->headers->all()),
            ];
        });
        $this->link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);
    }

    public function test_signed_edge_requests_resolve_on_the_forwarded_workspace_domain(): void
    {
        $this->withHeaders($this->edgeHeaders('edge-secret'))
            ->get('https://origin.openlink.test/launch')
            ->assertRedirect('https://example.com/launch');

        $this->assertSame(1, $this->link->fresh()->successful_visits);
    }

    public function test_any_configured_secret_is_accepted_during_rotation(): void
    {
        $this->withHeaders($this->edgeHeaders('previous-secret'))
            ->get('https://origin.openlink.test/launch')
            ->assertRedirect('https://example.com/launch');
    }

    public function test_signed_edge_requests_record_the_forwarded_visitor_country(): void
    {
        config(['openlink.analytics.via_queue' => false]);

        $this->withHeaders($this->edgeHeaders('edge-secret'))
            ->get('https://origin.openlink.test/launch')
            ->assertRedirect();

        $this->assertSame('FR', AnalyticsEvent::query()->where('short_link_id', $this->link->id)->value('country'));
    }

    public function test_signed_edge_requests_use_the_forwarded_visitor_ip(): void
    {
        config(['openlink.analytics.via_queue' => false]);

        foreach (['198.51.100.24', '203.0.113.99'] as $ip) {
            $this->withHeaders(['X-Openlink-Client-Ip' => $ip] + $this->edgeHeaders('edge-secret'))
                ->get('https://origin.openlink.test/launch')
                ->assertRedirect();
        }

        $this->assertSame(2, AnalyticsEvent::query()->where('short_link_id', $this->link->id)->distinct()->count('visitor_hash'));
    }

    public function test_requests_with_a_wrong_secret_keep_their_own_host(): void
    {
        $this->withHeaders($this->edgeHeaders('forged'))
            ->get('https://origin.openlink.test/launch')
            ->assertNotFound();

        $this->assertSame(0, $this->link->fresh()->successful_visits);
    }

    public function test_edge_headers_are_ignored_when_no_secret_is_configured(): void
    {
        config(['openlink.domain_edge.secrets' => []]);

        $this->withHeaders($this->edgeHeaders('edge-secret'))
            ->get('https://origin.openlink.test/launch')
            ->assertNotFound();

        $this->assertSame('origin.openlink.test', $this->seen['host']);
        $this->assertSame(0, $this->link->fresh()->successful_visits);
    }

    public function test_visitor_forwarded_headers_are_dropped_from_signed_requests(): void
    {
        $this->withHeaders([
            'X-Forwarded-Host' => 'evil.test',
            'X-Forwarded-Prefix' => '/evil',
            'X-Forwarded-Proto' => 'http',
            'X-Forwarded-Port' => '8080',
            'Forwarded' => 'for=6.6.6.6;host=evil.test;proto=http',
        ] + $this->edgeHeaders('edge-secret'))
            ->get('https://origin.openlink.test/launch')
            ->assertRedirect('https://example.com/launch');

        $this->assertSame('go.example.test', $this->seen['host']);
        $this->assertSame('198.51.100.24', $this->seen['ip']);
        $this->assertTrue($this->seen['secure']);
        $this->assertSame('https://go.example.test/probe', $this->seen['url']);
        $this->assertNotContains('x-forwarded-prefix', $this->seen['headers']);
        $this->assertNotContains('forwarded', $this->seen['headers']);
    }

    public function test_invalid_forwarded_host_and_ip_are_ignored(): void
    {
        $this->withHeaders([
            'X-Openlink-Host' => 'evil.test:8080/path',
            'X-Openlink-Client-Ip' => 'not-an-ip',
        ] + $this->edgeHeaders('edge-secret'))
            ->get('https://origin.openlink.test/launch')
            ->assertNotFound();

        $this->assertSame('origin.openlink.test', $this->seen['host']);
        $this->assertNotSame('172.70.1.1', $this->seen['ip']);
        $this->assertSame('https://origin.openlink.test/probe', $this->seen['url']);
    }

    public function test_edge_headers_never_reach_the_application_on_unsigned_requests(): void
    {
        $this->withHeaders($this->edgeHeaders('forged') + ['X-Openlink-Anything' => 'value'])
            ->get('https://origin.openlink.test/launch')
            ->assertNotFound();

        $this->assertSame([], array_values(array_filter($this->seen['headers'], fn (string $name): bool => str_starts_with($name, 'x-openlink-'))));
    }

    private function edgeHeaders(string $secret): array
    {
        return [
            'X-Forwarded-For' => '172.70.1.1',
            'X-Openlink-Edge-Secret' => $secret,
            'X-Openlink-Host' => 'go.example.test',
            'X-Openlink-Client-Ip' => '198.51.100.24',
            'X-Openlink-Country' => 'fr',
        ];
    }
}
