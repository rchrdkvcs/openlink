<?php

namespace Tests\Feature\Conversions;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Models\ConversionAttribution;
use App\Models\ShortLink;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Conversions\AttributionTokens;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ConversionRetentionTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00'));
    }

    public function test_pruning_keeps_expired_unconverted_visits_as_the_rate_denominator(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'goal', ['conversion_goal' => 'Registration']);
        $this->attribution($link, now()->subDays(3));
        $this->attribution($link, now()->subDays(3));
        $this->attribution($link, now()->subDays(3));
        $this->attribution($link, now()->subDays(3), converted: true);

        $this->artisan('openlink:prune-analytics')->assertSuccessful();

        $this->assertSame(4, ConversionAttribution::query()->count());
        $totals = app(BuildAnalyticsReport::class)
            ->report($workspace, AnalyticsFilters::fromRequest(Request::create('/?range=7d')))['conversions']['totals'];
        $this->assertSame(['attributed_visits' => 4, 'conversions' => 1, 'pending' => 0, 'rate' => 25.0], $totals);
    }

    public function test_pruning_removes_attributions_issued_before_the_retention_window(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'goal', ['conversion_goal' => 'Registration']);
        $kept = $this->attribution($link, now()->subDays(365));
        $keptConverted = $this->attribution($link, now()->subDays(200), converted: true);
        $this->attribution($link, now()->subDays(365)->subSecond());
        $this->attribution($link, now()->subDays(400), converted: true);

        $this->artisan('openlink:prune-analytics')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$kept->id, $keptConverted->id],
            ConversionAttribution::query()->pluck('id')->all(),
        );
    }

    public function test_deleting_a_workspace_deletes_its_attributions(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'goal', ['conversion_goal' => 'Registration']);
        $this->attribution($link, now(), converted: true);

        $workspace->delete();

        $this->assertSame(0, ConversionAttribution::query()->count());
    }

    private function attribution(ShortLink $link, $issuedAt, bool $converted = false): ConversionAttribution
    {
        return ConversionAttribution::query()->create([
            'workspace_id' => $link->workspace_id,
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'token_hash' => AttributionTokens::hash(Str::random(48)),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addHours(AttributionTokens::WINDOW_HOURS),
            'converted_at' => $converted ? $issuedAt->copy()->addHour() : null,
        ]);
    }
}
