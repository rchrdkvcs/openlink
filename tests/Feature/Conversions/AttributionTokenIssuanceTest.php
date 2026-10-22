<?php

namespace Tests\Feature\Conversions;

use App\Models\AnalyticsEvent;
use App\Models\ConversionAttribution;
use App\Models\RoutingRule;
use App\Services\ShortLinks\SmartRouting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\UserAgents;
use Tests\TestCase;

class AttributionTokenIssuanceTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00'));
        config(['openlink.analytics.via_queue' => true]);
        $this->withHeader('User-Agent', UserAgents::SAFARI_MAC);
    }

    public function test_links_without_a_conversion_goal_redirect_unchanged(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'plain', ['destination_url' => 'https://example.com/landing?ref=x']);

        $this->withHeaders(['Host' => 'localhost'])
            ->get('/plain')
            ->assertRedirect('https://example.com/landing?ref=x');

        $this->assertSame(0, ConversionAttribution::query()->count());
    }

    public function test_successful_visit_of_a_link_with_a_goal_carries_an_attribution_token(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'signup', [
            'destination_url' => 'https://example.com/register?plan=pro#form',
            'conversion_goal' => 'Registration',
        ]);

        $location = $this->withHeaders(['Host' => 'localhost'])->get('/signup')->assertRedirect()->headers->get('Location');

        $this->assertMatchesRegularExpression(
            '~^https://example\.com/register\?plan=pro&openlink_attribution=([A-Za-z0-9]{48})#form$~',
            $location,
        );
        $attribution = ConversionAttribution::query()->sole();
        $this->assertSame($link->id, $attribution->short_link_id);
        $this->assertSame($workspace->id, $attribution->workspace_id);
        $this->assertNull($attribution->converted_at);
        $this->assertSame('2026-10-21 12:00:00', $attribution->issued_at->toDateTimeString());
        $this->assertSame('2026-10-22 12:00:00', $attribution->expires_at->toDateTimeString());
        $this->assertStringNotContainsString($this->tokenFrom($location), json_encode($attribution->getAttributes()));
    }

    public function test_linked_qr_codes_redirect_unchanged_without_a_goal_and_attribute_scans_with_one(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'poster', ['destination_url' => 'https://example.com/poster']);
        $qrCode = $link->qrCodes()->create(['workspace_id' => $workspace->id, 'name' => 'Poster', 'token' => 'poster-token']);

        $this->get('/qr/poster-token')->assertRedirect('https://example.com/poster');
        $this->assertSame(0, ConversionAttribution::query()->count());

        $link->update(['conversion_goal' => 'Registration']);
        $location = $this->get('/qr/poster-token')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://example.com/poster?openlink_attribution=', $location);
        $attribution = ConversionAttribution::query()->sole();
        $this->assertSame($qrCode->id, $attribution->qr_code_id);
        $this->assertSame('scan', $attribution->metric);
    }

    public function test_password_prompts_and_failures_do_not_issue_tokens_but_an_unlocked_visit_does(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'locked', [
            'conversion_goal' => 'Registration',
            'password_hash' => Hash::make('opensesame'),
        ]);

        $this->withHeaders(['Host' => 'localhost'])->get('/locked')->assertOk();
        $this->withHeaders(['Host' => 'localhost'])
            ->post(route('public.password', $link), ['password' => 'wrong'])
            ->assertStatus(403);
        $this->assertSame(0, ConversionAttribution::query()->count());

        $location = $this->withHeaders(['Host' => 'localhost'])
            ->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('openlink_attribution=', $location);
        $this->assertSame(1, ConversionAttribution::query()->count());
    }

    public function test_blocked_links_and_fallback_redirects_do_not_issue_tokens(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'off', ['conversion_goal' => 'Registration', 'is_enabled' => false]);
        $this->shortLink($workspace, $domain, 'gone', [
            'conversion_goal' => 'Registration',
            'expires_at' => now()->subDay(),
            'fallback_url' => 'https://example.com/fallback',
        ]);
        $this->shortLink($workspace, $domain, 'full', [
            'conversion_goal' => 'Registration',
            'visit_limit' => 1,
            'successful_visits' => 1,
        ]);

        $this->withHeaders(['Host' => 'localhost'])->get('/off')->assertNotFound();
        $this->withHeaders(['Host' => 'localhost'])->get('/gone')->assertRedirect('https://example.com/fallback');
        $this->withHeaders(['Host' => 'localhost'])->get('/full')->assertNotFound();
        $this->withHeaders(['Host' => 'localhost'])->get('/missing')->assertNotFound();

        $this->assertSame(0, ConversionAttribution::query()->count());
    }

    public function test_bot_visits_are_not_attributed(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'crawled', ['conversion_goal' => 'Registration']);

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get('/crawled')
            ->assertRedirect('https://example.com/landing');

        $this->assertSame(0, ConversionAttribution::query()->count());
    }

    public function test_attribution_records_the_split_test_variant_that_served_the_visit(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'split', ['conversion_goal' => 'Registration']);
        app(SmartRouting::class)->sync($link, [[
            'name' => 'Pricing test', 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [],
            'variants' => [
                ['name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 50],
                ['name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 50],
            ],
        ]]);

        $location = $this->withHeaders(['Host' => 'localhost'])->get('/split')->assertRedirect()->headers->get('Location');

        $event = AnalyticsEvent::query()->sole();
        $attribution = ConversionAttribution::query()->sole();
        $variant = $attribution->routingVariant;
        $this->assertSame($event->routing_rule_id, $attribution->routing_rule_id);
        $this->assertSame($event->routing_variant_id, $attribution->routing_variant_id);
        $this->assertStringStartsWith($variant->destination_url.'?openlink_attribution=', $location);
        $this->assertArrayNotHasKey('visitor_hash', $attribution->getAttributes());
    }

    private function tokenFrom(string $location): string
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query['openlink_attribution'];
    }
}
