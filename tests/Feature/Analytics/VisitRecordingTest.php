<?php

namespace Tests\Feature\Analytics;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Models\AnalyticsEvent;
use App\Models\QrCode;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Outcome;
use App\Services\Analytics\ReferrerClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\UserAgents;
use Tests\TestCase;

class VisitRecordingTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_visit_records_a_fully_dimensioned_event_without_a_queue_worker(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->withHeaders([
            'Host' => 'localhost',
            'User-Agent' => UserAgents::CHROME_ANDROID,
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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'botcheck');

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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'qr-link');
        $qrCode = QrCode::create(['short_link_id' => $link->id, 'name' => 'Poster', 'token' => 'tok123']);

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => UserAgents::SAFARI_MAC])
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
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'gone', ['expires_at' => now()->subDay()]);

        $this->withHeaders(['Host' => 'localhost', 'User-Agent' => UserAgents::SAFARI_MAC])->get('/gone')->assertStatus(404);

        $this->assertDatabaseHas('analytics_events', ['outcome' => Outcome::EXPIRED, 'metric' => 'visit']);
    }
}
