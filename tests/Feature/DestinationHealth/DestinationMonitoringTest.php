<?php

namespace Tests\Feature\DestinationHealth;

use App\Enums\DestinationHealthStatus;
use App\Jobs\CheckDestination;
use App\Models\DestinationCheck;
use App\Models\RoutingRule;
use App\Services\Dns\DnsLookup;
use App\Services\InstanceSettings;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class DestinationMonitoringTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private InMemoryDnsLookup $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = (new InMemoryDnsLookup)->pointTo('example.com', '93.184.216.34');
        $this->app->instance(DnsLookup::class, $this->dns);
        Http::preventStrayRequests();
    }

    public function test_monitoring_is_scheduled(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'openlink:monitor-destinations'));

        $this->assertCount(1, $events);
        $this->assertSame('*/15 * * * *', $events->first()->expression);
    }

    public function test_disabled_monitoring_queues_nothing(): void
    {
        Queue::fake();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'launch');

        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(0, DestinationCheck::query()->count());
    }

    public function test_monitoring_discovers_and_deduplicates_monitored_destinations_per_workspace(): void
    {
        Queue::fake();
        $this->enableMonitoring();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch', [
            'destination_url' => 'https://example.com/a',
            'fallback_url' => 'https://example.com/fallback',
        ]);
        $this->shortLink($workspace, $domain, 'again', ['destination_url' => 'https://example.com/a']);
        $this->shortLink($workspace, $domain, 'old', ['destination_url' => 'https://example.com/archived', 'archived_at' => now()]);
        $link->routingRules()->create(['name' => 'iOS', 'position' => 1, 'destination_url' => 'https://example.com/ios']);
        $link->routingRules()->create(['name' => 'Off', 'position' => 2, 'is_enabled' => false, 'destination_url' => 'https://example.com/off']);
        $split = $link->routingRules()->create(['name' => 'Split', 'position' => 3, 'type' => RoutingRule::TYPE_SPLIT_TEST]);
        $split->variants()->create(['name' => 'B', 'position' => 1, 'destination_url' => 'https://example.com/b']);
        $split->variants()->create(['name' => 'C', 'position' => 2, 'is_enabled' => false, 'destination_url' => 'https://example.com/c']);
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('go.other.test');
        $this->shortLink($other, $otherDomain, 'launch', ['destination_url' => 'https://example.com/a']);

        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['https://example.com/a', 'https://example.com/fallback', 'https://example.com/ios', 'https://example.com/b'],
            DestinationCheck::query()->where('workspace_id', $workspace->id)->pluck('url')->all(),
        );
        $this->assertSame(['https://example.com/a'], DestinationCheck::query()->where('workspace_id', $other->id)->pluck('url')->all());
        Queue::assertPushed(CheckDestination::class, 5);
    }

    public function test_only_due_checks_are_queued_within_the_limit(): void
    {
        Queue::fake();
        $this->enableMonitoring();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        foreach (['fresh', 'old', 'confirming', 'confirmed-fresh'] as $slug) {
            $this->shortLink($workspace, $domain, $slug, ['destination_url' => "https://example.com/$slug"]);
        }
        $this->check($workspace->id, 'https://example.com/fresh', ['checked_at' => now()->subHour(), 'status' => 'reachable']);
        $old = $this->check($workspace->id, 'https://example.com/old', ['checked_at' => now()->subHours(7), 'status' => 'reachable']);
        $confirming = $this->check($workspace->id, 'https://example.com/confirming', ['checked_at' => now()->subMinutes(11), 'status' => 'http_failure', 'consecutive_failures' => 1]);
        $this->check($workspace->id, 'https://example.com/confirmed-fresh', ['checked_at' => now()->subMinutes(11), 'status' => 'http_failure', 'consecutive_failures' => 2, 'incident_opened_at' => now()->subMinutes(11)]);

        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        $pushed = Queue::pushed(CheckDestination::class)->map(fn (CheckDestination $job) => $job->destinationCheckId)->all();
        $this->assertEqualsCanonicalizing([$old->id, $confirming->id], $pushed);

        Queue::fake();
        DestinationCheck::query()->update(['checked_at' => now()->subDay(), 'requested_at' => null]);
        $this->artisan('openlink:monitor-destinations', ['--limit' => 2])->assertSuccessful();
        Queue::assertPushed(CheckDestination::class, 2);
    }

    public function test_failures_are_confirmed_before_an_incident_opens_and_recovery_is_recorded(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'launch', ['destination_url' => 'https://example.com/page']);
        $check = $this->check($workspace->id, 'https://example.com/page');
        $status = 404;
        Http::fake(function () use (&$status) {
            return Http::response('', $status);
        });

        CheckDestination::dispatchSync($check->id);
        $check->refresh();
        $this->assertSame(DestinationHealthStatus::HttpFailure, $check->status);
        $this->assertSame(404, $check->http_status);
        $this->assertSame(1, $check->consecutive_failures);
        $this->assertNotNull($check->failing_since);
        $this->assertFalse($check->hasIncident());

        CheckDestination::dispatchSync($check->id);
        $check->refresh();
        $this->assertSame(2, $check->consecutive_failures);
        $this->assertTrue($check->hasIncident());

        $status = 403;
        CheckDestination::dispatchSync($check->id);
        $check->refresh();
        $this->assertSame(DestinationHealthStatus::Inconclusive, $check->status);
        $this->assertTrue($check->hasIncident());
        $this->assertSame(2, $check->consecutive_failures);

        $status = 200;
        CheckDestination::dispatchSync($check->id);
        $check->refresh();
        $this->assertSame(DestinationHealthStatus::Reachable, $check->status);
        $this->assertFalse($check->hasIncident());
        $this->assertSame(0, $check->consecutive_failures);
        $this->assertNull($check->failing_since);
        $this->assertNotNull($check->recovered_at);
    }

    public function test_inconclusive_results_never_open_an_incident(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'launch', ['destination_url' => 'https://example.com/page']);
        $check = $this->check($workspace->id, 'https://example.com/page');
        Http::fake(fn () => Http::response('', 429));

        CheckDestination::dispatchSync($check->id);
        CheckDestination::dispatchSync($check->id);
        CheckDestination::dispatchSync($check->id);

        $check->refresh();
        $this->assertSame(DestinationHealthStatus::Inconclusive, $check->status);
        $this->assertSame(0, $check->consecutive_failures);
        $this->assertFalse($check->hasIncident());
    }

    public function test_checks_no_longer_referenced_after_a_destination_change_are_pruned(): void
    {
        Queue::fake();
        $this->enableMonitoring();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch', ['destination_url' => 'https://example.com/old']);
        $this->check($workspace->id, 'https://example.com/old', ['status' => 'http_failure', 'incident_opened_at' => now()]);

        $link->update(['destination_url' => 'https://example.com/new']);
        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        $this->assertSame(['https://example.com/new'], DestinationCheck::query()->pluck('url')->all());
    }

    public function test_unhealthy_destinations_never_change_public_resolution(): void
    {
        Http::fake();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'launch', ['destination_url' => 'https://example.com/broken']);
        $this->check($workspace->id, 'https://example.com/broken', [
            'status' => 'http_failure',
            'http_status' => 404,
            'consecutive_failures' => 5,
            'incident_opened_at' => now(),
            'checked_at' => now(),
        ]);

        $this->withHeader('Host', 'localhost')->get('/launch')->assertRedirect('https://example.com/broken');

        Http::assertNothingSent();
    }

    public function test_disabled_monitoring_skips_pruning_and_discovery(): void
    {
        Queue::fake();
        [$workspace] = $this->workspaceWithActiveDomain();
        $this->check($workspace->id, 'https://example.com/orphan');

        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        $this->assertSame(1, DestinationCheck::query()->count());
    }

    public function test_pending_checks_are_not_due_so_a_backlog_cannot_starve_other_checks(): void
    {
        Queue::fake();
        $this->enableMonitoring();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'one', ['destination_url' => 'https://example.com/one']);
        $this->shortLink($workspace, $domain, 'two', ['destination_url' => 'https://example.com/two']);
        $queued = $this->check($workspace->id, 'https://example.com/one', ['requested_at' => now()]);
        $waiting = $this->check($workspace->id, 'https://example.com/two', ['checked_at' => now()->subDay()]);

        $this->artisan('openlink:monitor-destinations', ['--limit' => 1])->assertSuccessful();

        $pushed = Queue::pushed(CheckDestination::class)->map(fn (CheckDestination $job) => $job->destinationCheckId)->all();
        $this->assertSame([$waiting->id], $pushed);
        $this->assertNotContains($queued->id, $pushed);
    }

    public function test_split_test_rule_destinations_do_not_count_as_references(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch', ['destination_url' => 'https://example.com/a']);
        $link->routingRules()->create(['name' => 'Split', 'position' => 1, 'type' => RoutingRule::TYPE_SPLIT_TEST, 'destination_url' => 'https://example.com/leftover']);
        $this->check($workspace->id, 'https://example.com/leftover', ['incident_opened_at' => now()]);

        $this->assertSame(0, DestinationCheck::query()->referenced()->count());
    }

    public function test_duplicate_checks_for_one_destination_are_queued_once(): void
    {
        Queue::fake();
        [$workspace] = $this->workspaceWithActiveDomain();
        $check = $this->check($workspace->id, 'https://example.com/page');

        dispatch(CheckDestination::for($check));
        dispatch(CheckDestination::for($check));

        Queue::assertPushed(CheckDestination::class, 1);
    }

    public function test_checks_for_the_same_host_never_run_concurrently(): void
    {
        [$workspace] = $this->workspaceWithActiveDomain();
        $first = CheckDestination::for($this->check($workspace->id, 'https://Example.com/a'));
        $sameHost = CheckDestination::for($this->check($workspace->id, 'https://example.com/b'));
        $otherHost = CheckDestination::for($this->check($workspace->id, 'https://other.example/b'));
        $sameHost->withFakeQueueInteractions();
        $otherHost->withFakeQueueInteractions();
        $ran = [];

        $this->throughMiddleware($first, function () use ($sameHost, $otherHost, &$ran) {
            $this->throughMiddleware($sameHost, function () use (&$ran) {
                $ran[] = 'same-host';
            });
            $this->throughMiddleware($otherHost, function () use (&$ran) {
                $ran[] = 'other-host';
            });
        });

        $this->assertSame(['other-host'], $ran);
        $sameHost->assertReleased();
        $otherHost->assertNotReleased();
    }

    public function test_checks_share_a_bounded_number_of_global_slots(): void
    {
        [$workspace] = $this->workspaceWithActiveDomain();
        $checks = collect(range(1, CheckDestination::CONCURRENCY + 1))
            ->map(fn (int $index) => CheckDestination::for($this->check($workspace->id, "https://host-$index.example/")));
        $first = $checks->first();
        $sharingSlot = $checks->first(fn (CheckDestination $job) => $job !== $first && $job->destinationCheckId % CheckDestination::CONCURRENCY === $first->destinationCheckId % CheckDestination::CONCURRENCY);
        $sharingSlot->withFakeQueueInteractions();
        $ran = false;

        $this->throughMiddleware($first, function () use ($sharingSlot, &$ran) {
            $this->throughMiddleware($sharingSlot, function () use (&$ran) {
                $ran = true;
            });
        });

        $this->assertFalse($ran);
        $sharingSlot->assertReleased();
    }

    public function test_checks_are_rate_limited_per_host(): void
    {
        [$workspace] = $this->workspaceWithActiveDomain();
        $ran = 0;

        foreach (range(1, 7) as $index) {
            $job = CheckDestination::for($this->check($workspace->id, "https://example.com/$index"))->withFakeQueueInteractions();
            $this->throughMiddleware($job, function () use (&$ran) {
                $ran++;
            });
        }

        $other = CheckDestination::for($this->check($workspace->id, 'https://other.example/'))->withFakeQueueInteractions();
        $this->throughMiddleware($other, function () use (&$ran) {
            $ran++;
        });

        $this->assertSame(7, $ran);
        $job->assertReleased();
    }

    private function throughMiddleware(CheckDestination $job, callable $handle): void
    {
        (new Pipeline($this->app))->send($job)->through($job->middleware())->then(fn () => $handle());
    }

    private function enableMonitoring(): void
    {
        $this->app->make(InstanceSettings::class)->set('destination_monitoring', true);
    }

    private function check(int $workspaceId, string $url, array $attributes = []): DestinationCheck
    {
        return DestinationCheck::query()->create([
            'workspace_id' => $workspaceId,
            'url' => $url,
            'url_hash' => DestinationCheck::hashFor($url),
            ...$attributes,
        ]);
    }
}
