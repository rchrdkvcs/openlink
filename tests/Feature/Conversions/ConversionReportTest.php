<?php

namespace Tests\Feature\Conversions;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Models\ConversionAttribution;
use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Models\WorkspaceMember;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Conversions\AttributionTokens;
use App\Services\ShortLinks\SmartRouting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ConversionReportTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_report_separates_attributed_visits_conversions_rates_and_open_windows(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'pricing', ['conversion_goal' => 'Registration']);
        app(SmartRouting::class)->sync($link, [[
            'name' => 'Pricing test', 'type' => RoutingRule::TYPE_SPLIT_TEST, 'conditions' => [],
            'variants' => [
                ['name' => 'A', 'destination_url' => 'https://example.com/a', 'weight' => 50],
                ['name' => 'B', 'destination_url' => 'https://example.com/b', 'weight' => 50],
            ],
        ]]);
        $rule = $link->routingRules()->with('variants')->sole();
        [$a, $b] = $rule->variants->all();

        $this->attributions($link, 40, converted: 10, issuedAt: now()->subDays(3), variant: $a);
        $this->attributions($link, 4, converted: 1, issuedAt: now()->subDays(3), variant: $b);
        $this->attributions($link, 2, converted: 0, issuedAt: now()->subHour(), variant: $b);
        $this->attributions($link, 5, converted: 5, issuedAt: now()->subDays(60), variant: $a);

        $conversions = $this->report($workspace)['conversions'];

        $this->assertTrue($conversions['enabled']);
        $this->assertSame(AttributionTokens::WINDOW_HOURS, $conversions['window_hours']);
        $this->assertSame(30, $conversions['minimum_attributed_visits']);
        $this->assertSame(['attributed_visits' => 46, 'conversions' => 11, 'pending' => 2, 'rate' => 23.9], $conversions['totals']);
        $this->assertSame([[
            'id' => $link->id,
            'slug' => 'pricing',
            'short_url' => 'https://localhost/pricing',
            'conversion_goal' => 'Registration',
            'attributed_visits' => 46,
            'conversions' => 11,
            'pending' => 2,
            'rate' => 23.9,
            'insufficient_evidence' => false,
        ]], $conversions['links']);

        $routing = collect($conversions['routing'])->keyBy('variant_name');
        $this->assertSame(
            ['rule_name' => 'Pricing test', 'attributed_visits' => 40, 'conversions' => 10, 'pending' => 0, 'rate' => 25.0, 'insufficient_evidence' => false],
            collect($routing['A'])->only(['rule_name', 'attributed_visits', 'conversions', 'pending', 'rate', 'insufficient_evidence'])->all(),
        );
        $this->assertSame(
            ['attributed_visits' => 6, 'conversions' => 1, 'pending' => 2, 'rate' => 16.7, 'insufficient_evidence' => true],
            collect($routing['B'])->only(['attributed_visits', 'conversions', 'pending', 'rate', 'insufficient_evidence'])->all(),
        );
        $this->assertStringNotContainsString('winner', json_encode($conversions));
    }

    public function test_report_is_scoped_to_the_workspace_and_follows_filters(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $signup = $this->shortLink($workspace, $domain, 'signup', ['conversion_goal' => 'Registration']);
        $demo = $this->shortLink($workspace, $domain, 'demo', ['conversion_goal' => 'Demo request']);
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->shortLink($other, $otherDomain, 'foreign', ['conversion_goal' => 'Registration']);

        $this->attributions($signup, 3, converted: 1, issuedAt: now()->subDay());
        $this->attributions($demo, 2, converted: 2, issuedAt: now()->subDay());
        $this->attributions($foreign, 9, converted: 9, issuedAt: now()->subDay());

        $all = $this->report($workspace)['conversions'];
        $filtered = $this->report($workspace, 'link='.$demo->id)['conversions'];

        $this->assertSame(['attributed_visits' => 5, 'conversions' => 3, 'pending' => 0, 'rate' => 60.0], $all['totals']);
        $this->assertEqualsCanonicalizing([$signup->id, $demo->id], array_column($all['links'], 'id'));
        $this->assertTrue($all['links'][0]['insufficient_evidence']);
        $this->assertSame(['attributed_visits' => 2, 'conversions' => 2, 'pending' => 0, 'rate' => 100.0], $filtered['totals']);
        $this->assertSame([$demo->id], array_column($filtered['links'], 'id'));
    }

    public function test_workspaces_without_goals_report_conversions_as_disabled(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'plain');

        $conversions = $this->report($workspace)['conversions'];

        $this->assertFalse($conversions['enabled']);
        $this->assertSame(['attributed_visits' => 0, 'conversions' => 0, 'pending' => 0, 'rate' => null], $conversions['totals']);
        $this->assertSame([], $conversions['links']);
        $this->assertSame([], $conversions['routing']);
    }

    public function test_viewers_see_conversions_on_the_analytics_page(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'signup', ['conversion_goal' => 'Registration']);
        $this->attributions($link, 2, converted: 1, issuedAt: now()->subDay());

        $this->actingInWorkspace($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER), $workspace)
            ->withHeader('Host', 'localhost')
            ->get('/analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Analytics/Index')
                ->where('report.conversions.totals.conversions', 1)
                ->where('report.conversions.links.0.insufficient_evidence', true));
    }

    private function report($workspace, string $query = ''): array
    {
        return app(BuildAnalyticsReport::class)->report($workspace, AnalyticsFilters::fromRequest(Request::create('/?'.$query)));
    }

    private function attributions(ShortLink $link, int $count, int $converted, $issuedAt, $variant = null): void
    {
        foreach (range(1, $count) as $index) {
            ConversionAttribution::query()->create([
                'workspace_id' => $link->workspace_id,
                'short_link_id' => $link->id,
                'routing_rule_id' => $variant?->routing_rule_id,
                'routing_variant_id' => $variant?->id,
                'metric' => 'visit',
                'token_hash' => AttributionTokens::hash(Str::random(48)),
                'issued_at' => $issuedAt,
                'expires_at' => CarbonImmutable::parse($issuedAt)->addHours(AttributionTokens::WINDOW_HOURS),
                'converted_at' => $index <= $converted ? CarbonImmutable::parse($issuedAt)->addMinutes(10) : null,
            ]);
        }
    }
}
