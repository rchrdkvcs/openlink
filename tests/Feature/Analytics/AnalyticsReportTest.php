<?php

namespace Tests\Feature\Analytics;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Outcome;
use App\Services\Analytics\Report\AnalyticsEventSlice;
use App\Services\Analytics\Report\BreakdownSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class AnalyticsReportTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_report_summarises_timeseries_breakdowns_and_top_links(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'report-link');

        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(2), 'visitor_hash' => 'aaa', 'country' => 'FR', 'browser' => 'Chrome']);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(2), 'visitor_hash' => 'aaa', 'country' => 'FR', 'browser' => 'Chrome']);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDay(), 'visitor_hash' => 'bbb', 'country' => 'DE', 'browser' => 'Firefox']);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDay(), 'outcome' => Outcome::DISABLED]);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDay(), 'outcome' => Outcome::PASSWORD_REQUIRED]);

        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(40), 'visitor_hash' => 'ccc']);

        $report = app(BuildAnalyticsReport::class)->report($workspace, AnalyticsFilters::fromRequest(Request::create('/?range=30d')));

        $this->assertSame(3, $report['summary']['visits']);
        $this->assertSame(2, $report['summary']['visitors']);
        $this->assertSame(1, $report['summary']['blocked']);
        $this->assertSame(200.0, $report['summary']['visits_change']);
        $this->assertSame(75.0, $report['summary']['success_rate']);
        $this->assertSame(1, $report['summary']['active_links']);

        $days = collect($report['timeseries']);
        $this->assertSame(31, $days->count());
        $this->assertSame(2, $days->firstWhere('bucket', now()->subDays(2)->toDateString())['visits']);
        $this->assertSame(1, $days->firstWhere('bucket', now()->subDay()->toDateString())['blocked']);

        $countries = collect($report['breakdowns']['countries']);
        $this->assertSame(['FR', 'DE'], $countries->pluck('label')->all());
        $this->assertSame(66.7, $countries->firstWhere('label', 'FR')['share']);

        $this->assertSame($link->id, $report['top_links'][0]['id']);
        $this->assertSame(3, $report['top_links'][0]['visits']);

        $outcomes = collect($report['outcomes']);
        $this->assertSame(3, $outcomes->firstWhere('outcome', Outcome::SUCCESS)['count']);
        $this->assertSame(1, $outcomes->firstWhere('outcome', Outcome::PASSWORD_REQUIRED)['count']);
        $this->assertSame(1, $outcomes->firstWhere('outcome', Outcome::DISABLED)['count']);
    }

    public function test_breakdown_shares_include_rows_beyond_the_limit_with_one_event_query(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'breakdown');

        foreach (range(0, 12) as $index) {
            $this->analyticsEvent($link, ['country' => 'C'.strtoupper(dechex($index))]);
        }

        $this->analyticsEvent($link, ['country' => 'C0']);
        $this->analyticsEvent($link, ['country' => 'C0', 'is_bot' => true]);
        $this->analyticsEvent($link, ['country' => null]);

        $slice = new AnalyticsEventSlice($workspace, AnalyticsFilters::fromRequest(Request::create('/')));
        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        try {
            $rows = app(BreakdownSection::class)->dimension($slice, 'country');
            $queries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        $this->assertCount(1, $queries);
        $this->assertCount(12, $rows);
        $this->assertSame('C0', $rows[0]['label']);
        $this->assertSame(2, $rows[0]['count']);
        $this->assertSame(14.3, $rows[0]['share']);
    }

    public function test_report_filters_by_link_and_metric(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $linkA = $this->shortLink($workspace, $domain, 'link-a');
        $linkB = $this->shortLink($workspace, $domain, 'link-b');

        $this->analyticsEvent($linkA);
        $this->analyticsEvent($linkB);
        $this->analyticsEvent($linkB, ['metric' => 'scan']);

        $reporter = app(BuildAnalyticsReport::class);

        $byLink = $reporter->summary($workspace, AnalyticsFilters::fromRequest(Request::create('/?link='.$linkA->id)));
        $this->assertSame(1, $byLink['visits']);
        $this->assertSame(0, $byLink['scans']);

        $byMetric = $reporter->summary($workspace, AnalyticsFilters::fromRequest(Request::create('/?metric=scan')));
        $this->assertSame(0, $byMetric['visits']);
        $this->assertSame(1, $byMetric['scans']);
    }
}
