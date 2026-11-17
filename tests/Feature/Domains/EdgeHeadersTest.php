<?php

namespace Tests\Feature\Domains;

use App\Enums\DomainStatus;
use App\Models\AnalyticsEvent;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\TestCase;

class EdgeHeadersTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    private ShortLink $link;

    protected function setUp(): void
    {
        parent::setUp();

        config(['openlink.domain_edge.secrets' => ['previous-secret', 'edge-secret']]);

        [$workspace, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
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

        $this->withHeaders($this->edgeHeaders(''))
            ->get('https://origin.openlink.test/launch')
            ->assertNotFound();
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
