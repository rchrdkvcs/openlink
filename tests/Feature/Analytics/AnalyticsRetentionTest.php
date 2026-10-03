<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class AnalyticsRetentionTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_analytics_retention_command_prunes_old_events_only(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'old', ['destination_url' => 'https://example.com/old']);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(400)]);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(10)]);

        $this->artisan('openlink:prune-analytics')->assertSuccessful();

        $this->assertSame(1, AnalyticsEvent::query()->count());
        $this->assertTrue(AnalyticsEvent::query()->sole()->occurred_at->greaterThan(now()->subDays(365)));
    }
}
