<?php

namespace Tests\Feature;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Outcome;
use App\Services\Analytics\ReferrerClassifier;
use App\Services\Analytics\UserAgentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';

    private const SAFARI_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15';

    public function test_visit_records_a_fully_dimensioned_event_without_a_queue_worker(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'launch');

        $this->withHeaders([
            'Host' => 'localhost',
            'User-Agent' => self::CHROME_ANDROID,
            'Referer' => 'https://www.facebook.com/some/post',
            'CF-IPCountry' => 'fr',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ])->get('/launch?utm_source=newsletter&utm_campaign=Spring%20Launch')
            ->assertRedirect('https://example.com/landing');

        $event = AnalyticsEvent::query()->sole();

        $this->assertSame($workspace->id, $event->workspace_id);
        $this->assertSame($link->id, $event->short_link_id);
        $this->assertSame($domain->id, $event->domain_id);
        $this->assertSame('visit', $event->metric);
        $this->assertSame(Outcome::SUCCESS, $event->outcome);
        $this->assertFalse($event->is_bot);
        $this->assertSame('facebook.com', $event->referrer_host);
        $this->assertSame(ReferrerClassifier::CHANNEL_SOCIAL, $event->referrer_channel);
        $this->assertSame('FR', $event->country);
        $this->assertSame('fr', $event->language);
        $this->assertSame('mobile', $event->device_type);
        $this->assertSame('Chrome', $event->browser);
        $this->assertSame('Android', $event->os);
        $this->assertSame('newsletter', $event->utm_source);
        $this->assertSame('Spring Launch', $event->utm_campaign);
        $this->assertNotNull($event->visitor_hash);
    }

    public function test_bot_traffic_is_flagged_and_excluded_from_report_figures(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $this->link($workspace, $domain, 'botcheck');

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => 'Twitterbot/1.0'])
            ->get('/botcheck')
            ->assertRedirect('https://example.com/landing');

        $this->assertTrue(AnalyticsEvent::query()->sole()->is_bot);

        $report = app(BuildAnalyticsReport::class)->report($workspace, AnalyticsFilters::fromRequest(Request::create('/')));

        $this->assertSame(0, $report['summary']['visits']);
        $this->assertSame(1, $report['summary']['bots']);
    }

    public function test_qr_scan_records_scan_metric_with_qr_code_id(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'qr-link');
        $qrCode = QrCode::create(['short_link_id' => $link->id, 'name' => 'Poster', 'token' => 'tok123']);

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => self::SAFARI_MAC])
            ->get('/qr/'.$qrCode->token)
            ->assertRedirect('https://example.com/landing');

        $this->assertDatabaseHas('analytics_events', [
            'metric' => 'scan',
            'qr_code_id' => $qrCode->id,
            'short_link_id' => $link->id,
            'outcome' => Outcome::SUCCESS,
        ]);
    }

    public function test_blocked_resolutions_record_their_outcome(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $this->link($workspace, $domain, 'gone', ['expires_at' => now()->subDay()]);

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => self::SAFARI_MAC])->get('/gone')->assertStatus(404);

        $this->assertDatabaseHas('analytics_events', ['outcome' => Outcome::EXPIRED, 'metric' => 'visit']);
    }

    public function test_report_summarises_timeseries_breakdowns_and_top_links(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'report-link');

        $this->event($workspace, $link, ['occurred_at' => now()->subDays(2), 'visitor_hash' => 'aaa', 'country' => 'FR', 'browser' => 'Chrome']);
        $this->event($workspace, $link, ['occurred_at' => now()->subDays(2), 'visitor_hash' => 'aaa', 'country' => 'FR', 'browser' => 'Chrome']);
        $this->event($workspace, $link, ['occurred_at' => now()->subDay(), 'visitor_hash' => 'bbb', 'country' => 'DE', 'browser' => 'Firefox']);
        $this->event($workspace, $link, ['occurred_at' => now()->subDay(), 'outcome' => Outcome::DISABLED]);
        // Previous period traffic for the delta baseline.
        $this->event($workspace, $link, ['occurred_at' => now()->subDays(40), 'visitor_hash' => 'ccc']);

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
        $this->assertSame(1, $outcomes->firstWhere('outcome', Outcome::DISABLED)['count']);
    }

    public function test_report_filters_by_link_and_metric(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $linkA = $this->link($workspace, $domain, 'link-a');
        $linkB = $this->link($workspace, $domain, 'link-b');

        $this->event($workspace, $linkA);
        $this->event($workspace, $linkB);
        $this->event($workspace, $linkB, ['metric' => 'scan']);

        $reporter = app(BuildAnalyticsReport::class);

        $byLink = $reporter->summary($workspace, AnalyticsFilters::fromRequest(Request::create('/?link='.$linkA->id)));
        $this->assertSame(1, $byLink['visits']);
        $this->assertSame(0, $byLink['scans']);

        $byMetric = $reporter->summary($workspace, AnalyticsFilters::fromRequest(Request::create('/?metric=scan')));
        $this->assertSame(0, $byMetric['visits']);
        $this->assertSame(1, $byMetric['scans']);
    }

    public function test_viewer_sees_analytics_for_links_in_folders(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Private']);
        $secretLink = $this->link($workspace, $domain, 'secret', ['folder_id' => $folder->id]);
        $openLink = $this->link($workspace, $domain, 'open');

        $this->event($workspace, $secretLink);
        $this->event($workspace, $openLink);

        $viewer = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => WorkspaceMember::ROLE_VIEWER]);

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
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $this->link($workspace, $domain, 'page-link');

        $this->actingAs($owner)
            ->withHeader('Host', 'localhost')
            ->get('/analytics?range=7d')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Analytics/Index')
                ->where('report.range.preset', '7d')
                ->has('report.summary')
                ->has('report.timeseries')
                ->has('filterOptions.links', 1));
    }

    public function test_editor_overview_displays_workspace_link_statistics(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $editor = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $editor->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $link = $this->link($workspace, $domain, 'editor-stats', ['folder_id' => $folder->id]);
        $this->event($workspace, $link);

        $this->actingAs($editor)
            ->withSession(['workspace_id' => $workspace->id])
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
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $link = $this->link($workspace, $domain, 'csv-link');
        $this->event($workspace, $link, ['country' => 'BE', 'browser' => 'Firefox']);

        $response = $this->actingAs($owner)->withHeader('Host', 'localhost')->get('/analytics/export?range=30d');

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('occurred_at,metric,outcome', $csv);
        $this->assertStringContainsString('csv-link', $csv);
        $this->assertStringContainsString('BE', $csv);
    }

    public function test_user_agent_parser_covers_common_agents(): void
    {
        $parser = new UserAgentParser;

        $chrome = $parser->parse(self::CHROME_ANDROID);
        $this->assertSame(['browser' => 'Chrome', 'os' => 'Android', 'device_type' => 'mobile', 'is_bot' => false], $chrome);

        $safari = $parser->parse(self::SAFARI_MAC);
        $this->assertSame(['browser' => 'Safari', 'os' => 'macOS', 'device_type' => 'desktop', 'is_bot' => false], $safari);

        $bot = $parser->parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->assertTrue($bot['is_bot']);
        $this->assertSame('bot', $bot['device_type']);

        $curl = $parser->parse('curl/8.5.0');
        $this->assertTrue($curl['is_bot']);
    }

    public function test_referrer_classifier_maps_channels(): void
    {
        $classifier = new ReferrerClassifier;

        $this->assertSame(['host' => null, 'channel' => 'direct'], $classifier->classify(null));
        $this->assertSame(['host' => 'google.com', 'channel' => 'search'], $classifier->classify('https://www.google.com/search?q=x'));
        $this->assertSame(['host' => 't.co', 'channel' => 'social'], $classifier->classify('https://t.co/abc'));
        $this->assertSame(['host' => 'youtube.com', 'channel' => 'video'], $classifier->classify('https://www.youtube.com/watch?v=1'));
        $this->assertSame(['host' => 'chatgpt.com', 'channel' => 'ai'], $classifier->classify('https://chatgpt.com/'));
        $this->assertSame(['host' => 'example.org', 'channel' => 'referral'], $classifier->classify('https://example.org/blog'));
    }

    /** @return array{0: Workspace, 1: Domain, 2: User} */
    private function workspaceAndDomain(string $hostname = 'localhost'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Analytics Co',
            'slug' => 'analytics-co',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $user];
    }

    private function link(Workspace $workspace, Domain $domain, string $slug, array $attributes = []): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/landing',
            ...$attributes,
        ]);
    }

    private function event(Workspace $workspace, ShortLink $link, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'workspace_id' => $workspace->id,
            'short_link_id' => $link->id,
            'domain_id' => $link->domain_id,
            'occurred_at' => now(),
            'metric' => 'visit',
            'outcome' => Outcome::SUCCESS,
            'is_bot' => false,
            'visitor_hash' => str()->random(32),
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'referrer_channel' => 'direct',
            ...$attributes,
        ]);
    }
}
