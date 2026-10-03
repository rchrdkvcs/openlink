<?php

namespace Tests\Feature\Analytics;

use App\Models\Folder;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class AnalyticsPagesTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_viewer_sees_analytics_for_links_in_folders(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Private']);
        $secretLink = $this->shortLink($workspace, $domain, 'secret', ['folder_id' => $folder->id]);
        $openLink = $this->shortLink($workspace, $domain, 'open');

        $this->analyticsEvent($secretLink);
        $this->analyticsEvent($openLink);

        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAs($viewer)
            ->withHeader('Host', 'localhost')
            ->get('/analytics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.visits', 2)
                ->has('filterOptions.links', 2)
                ->has('filterOptions.folders', 1));
    }

    public function test_analytics_page_renders_with_report_and_filter_options(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'page-link');

        $this->actingAs($owner)
            ->withHeader('Host', 'localhost')
            ->get('/analytics?range=7d')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Analytics/Index')
                ->where('report.range.preset', '7d')
                ->has('report.summary')
                ->has('report.timeseries')
                ->has('filterOptions.links', 1)
                ->where('filterOptions.links.0.short_url', 'https://localhost/page-link')
                ->where('filterOptions.links.0.destination_url', 'https://example.com/landing')
                ->has('filterOptions.qrCodes', 0));
    }

    public function test_editor_overview_displays_workspace_link_statistics(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $link = $this->shortLink($workspace, $domain, 'editor-stats', ['folder_id' => $folder->id]);
        $this->analyticsEvent($link);

        $this->actingInWorkspace($editor, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('analytics.summary.visits', 1)
                ->has('analytics.timeseries')
                ->has('analytics.top_links', 1));
    }

    public function test_csv_export_streams_filtered_events(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'csv-link');
        $this->analyticsEvent($link, ['country' => 'BE', 'browser' => 'Firefox']);

        $response = $this->actingAs($owner)->withHeader('Host', 'localhost')->get('/analytics/export?range=30d');

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('occurred_at,metric,outcome', $csv);
        $this->assertStringContainsString('csv-link', $csv);
        $this->assertStringContainsString('BE', $csv);
    }
}
