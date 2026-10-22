<?php

namespace Tests\Feature\DestinationHealth;

use App\Actions\DestinationHealth\DestinationHealthPayload;
use App\Enums\DestinationHealthStatus;
use App\Jobs\CheckDestination;
use App\Models\DestinationCheck;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\DestinationHealth\DestinationProbe;
use App\Services\Dns\DnsLookup;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class DestinationHealthPagesTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    public function test_editor_can_request_checks_for_every_destination_of_a_short_link(): void
    {
        Queue::fake();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $link = $this->shortLink($workspace, $domain, 'launch', [
            'destination_url' => 'https://example.com/a',
            'fallback_url' => 'https://example.com/fallback',
        ]);
        $link->routingRules()->create(['name' => 'iOS', 'position' => 1, 'destination_url' => 'https://example.com/a']);

        $this->actingInWorkspace($editor, $workspace)
            ->post(route('short-links.destination-checks.store', $link))
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['https://example.com/a', 'https://example.com/fallback'],
            DestinationCheck::query()->pluck('url')->all(),
        );
        $this->assertTrue(DestinationCheck::query()->get()->every->isPending());
        Queue::assertPushed(CheckDestination::class, 2);

        $this->actingInWorkspace($editor, $workspace)->post(route('short-links.destination-checks.store', $link));
        Queue::assertPushed(CheckDestination::class, 2);
    }

    public function test_a_manual_check_waits_for_a_real_worker_and_publishes_its_result_after_a_backlog(): void
    {
        config(['queue.default' => 'database']);
        $this->app->instance(DnsLookup::class, (new InMemoryDnsLookup)->pointTo('example.com', '93.184.216.34'));
        Http::fake(function () {
            $check = DestinationCheck::query()->firstOrFail();
            $this->assertSame('running', $check->executionState());
            $this->assertNotNull($check->started_at);
            $this->assertSame('running', app(DestinationHealthPayload::class)->check($check)['execution_state']);

            return Http::response('', 404);
        });
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.destination-checks.store', $link))
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseCount('jobs', 1);
        $this->travel(11)->minutes();

        $this->get(route('links.index'))->assertInertia(fn (Assert $page) => $page
            ->where('destinationHealth.0.status', 'unchecked')
            ->where('destinationHealth.0.pending', true)
            ->where('destinationHealth.0.execution_state', 'queued'));

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->get(route('links.index'))->assertInertia(fn (Assert $page) => $page
            ->where('destinationHealth.0.status', 'http_failure')
            ->where('destinationHealth.0.http_status', 404)
            ->where('destinationHealth.0.pending', false)
            ->where('destinationHealth.0.execution_state', 'completed'));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_an_unexpected_worker_failure_preserves_the_last_result_and_allows_a_retry(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $checkedAt = now()->subHour()->startOfSecond();
        $check = $this->check($workspace->id, $link->destination_url, [
            'status' => 'reachable', 'http_status' => 200, 'checked_at' => $checkedAt,
        ]);
        $this->mock(DestinationProbe::class)->shouldReceive('check')->once()->andThrow(new RuntimeException('worker failed'));

        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link))->assertRedirect();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->assertSame('failed', $check->fresh()->executionState());
        $this->assertFalse($check->fresh()->isPending());
        $this->assertTrue($check->fresh()->checked_at->equalTo($checkedAt));
        $this->get(route('links.index'))->assertInertia(fn (Assert $page) => $page
            ->where('destinationHealth.0.execution_state', 'failed')
            ->where('destinationHealth.0.status', 'reachable')
            ->where('destinationHealth.0.http_status', 200));

        $this->post(route('short-links.destination-checks.store', $link))->assertRedirect();
        $this->assertSame('queued', $check->fresh()->executionState());
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_synchronous_configuration_does_not_run_a_destination_check_in_the_request(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.destination-checks.store', $link))
            ->assertSessionHasErrors('destination_health');

        Http::assertNothingSent();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertFalse(DestinationCheck::query()->firstOrFail()->isPending());
    }

    public function test_queue_failure_is_reported_and_can_be_retried_without_a_stranded_unique_lock(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $checkedAt = now()->subHour()->startOfSecond();
        $previous = $this->check($workspace->id, $link->destination_url, [
            'status' => 'inconclusive', 'http_status' => 403, 'checked_at' => $checkedAt,
        ]);
        Queue::shouldReceive('connection')->once()->andThrow(new RuntimeException('queue unavailable'));

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.destination-checks.store', $link))
            ->assertSessionHasErrors('destination_health');

        $check = DestinationCheck::query()->firstOrFail();
        $this->assertSame('failed', $check->executionState());
        $this->assertSame(DestinationHealthStatus::Inconclusive, $previous->fresh()->status);
        $this->assertSame(403, $previous->fresh()->http_status);
        $this->assertTrue($previous->fresh()->checked_at->equalTo($checkedAt));
        $this->assertStringNotContainsString('queue unavailable', session('errors')->first('destination_health'));
        Queue::clearResolvedInstance('queue');
        $this->app->forgetInstance('queue');
        $this->post(route('short-links.destination-checks.store', $link))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_a_recheck_in_the_same_second_stays_pending_until_the_worker_completes(): void
    {
        $this->freezeSecond();
        Queue::fake();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $check = $this->check($workspace->id, $link->destination_url, ['status' => 'reachable', 'checked_at' => now()]);

        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $this->assertTrue($check->fresh()->isPending());
        $this->assertSame('queued', $check->fresh()->executionState());
    }

    public function test_a_recheck_can_be_queued_while_the_previous_job_still_holds_its_unique_lock(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $generation = $check->generation;
        $check->update(['execution_state' => 'completed', 'checked_at' => now(), 'status' => 'reachable']);

        $this->post(route('short-links.destination-checks.store', $link))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('jobs', 2);
        $this->assertNotSame($generation, $check->fresh()->generation);
        $this->assertSame('queued', $check->fresh()->executionState());
    }

    public function test_viewers_and_other_workspaces_cannot_request_checks(): void
    {
        Queue::fake();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $link = $this->shortLink($workspace, $domain, 'launch');
        $check = $this->check($workspace->id, 'https://example.com/landing');
        [$other, $outsider] = $this->workspaceWithOwner('Other');

        $this->actingInWorkspace($viewer, $workspace)->post(route('short-links.destination-checks.store', $link))->assertForbidden();
        $this->actingInWorkspace($viewer, $workspace)->post(route('destination-checks.retry', $check))->assertForbidden();
        $this->actingInWorkspace($outsider, $other)->post(route('short-links.destination-checks.store', $link))->assertForbidden();
        $this->actingInWorkspace($outsider, $other)->post(route('destination-checks.retry', $check))->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertFalse($check->fresh()->isPending());
    }

    public function test_editor_can_retry_a_destination_check(): void
    {
        Queue::fake();
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $this->shortLink($workspace, $domain, 'launch');
        $check = $this->check($workspace->id, 'https://example.com/landing', ['checked_at' => now()->subHour()]);

        $this->actingInWorkspace($editor, $workspace)->post(route('destination-checks.retry', $check))->assertRedirect();

        Queue::assertPushed(CheckDestination::class, fn (CheckDestination $job) => $job->destinationCheckId === $check->id);
        $this->assertTrue($check->fresh()->isPending());
    }

    public function test_issues_view_lists_confirmed_incidents_by_recent_traffic_with_affected_links_and_qr_codes(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $quiet = $this->shortLink($workspace, $domain, 'quiet', ['destination_url' => 'https://example.com/quiet']);
        $busy = $this->shortLink($workspace, $domain, 'busy', ['destination_url' => 'https://example.com/busy']);
        $shared = $this->shortLink($workspace, $domain, 'shared', [
            'destination_url' => 'https://example.com/ok',
            'fallback_url' => 'https://example.com/busy',
        ]);
        $this->shortLink($workspace, $domain, 'confirming', ['destination_url' => 'https://example.com/confirming']);
        $this->shortLink($workspace, $domain, 'fixed', ['destination_url' => 'https://example.com/fixed']);
        $busy->qrCodes()->create(['workspace_id' => $workspace->id, 'name' => 'Poster', 'token' => 'poster-token']);
        $this->analyticsEvent($busy);
        $this->analyticsEvent($busy, ['metric' => 'scan']);
        $this->analyticsEvent($quiet, ['occurred_at' => now()->subDays(30)]);
        $quietCheck = $this->check($workspace->id, 'https://example.com/quiet', $this->incident(now()->subDays(2)));
        $busyCheck = $this->check($workspace->id, 'https://example.com/busy', $this->incident(now()->subHour()));
        $this->check($workspace->id, 'https://example.com/confirming', ['status' => 'http_failure', 'consecutive_failures' => 1, 'checked_at' => now()]);
        $fixed = $this->check($workspace->id, 'https://example.com/fixed', ['status' => 'reachable', 'recovered_at' => now()->subHour(), 'checked_at' => now()]);
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('go.other.test');
        $this->shortLink($other, $otherDomain, 'leak', ['destination_url' => 'https://example.com/leak']);
        $this->check($other->id, 'https://example.com/leak', $this->incident(now()));

        $this->actingInWorkspace($viewer, $workspace)
            ->get(route('destination-issues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Links/Issues')
                ->has('issues.open', 2)
                ->where('issues.open.0.id', $busyCheck->id)
                ->where('issues.open.0.status', 'http_failure')
                ->where('issues.open.0.recent_traffic', 2)
                ->has('issues.open.0.references', 2)
                ->where('issues.open.0.references.0.short_link.id', $busy->id)
                ->where('issues.open.0.references.0.role', 'destination')
                ->where('issues.open.0.references.0.qr_codes.0.name', 'Poster')
                ->where('issues.open.0.references.1.short_link.id', $shared->id)
                ->where('issues.open.0.references.1.role', 'fallback')
                ->where('issues.open.1.id', $quietCheck->id)
                ->where('issues.open.1.recent_traffic', 0)
                ->has('issues.recovered', 1)
                ->where('issues.recovered.0.id', $fixed->id)
                ->where('monitoringEnabled', false)
                ->where('navigation.destination_issues_count', 2));
    }

    public function test_links_page_reports_health_only_for_current_destinations(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch', ['destination_url' => 'https://example.com/old']);
        $this->check($workspace->id, 'https://example.com/old', $this->incident(now()));

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('links.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('destinationHealth', 1)
                ->where('destinationHealth.0.url', 'https://example.com/old')
                ->where('destinationHealth.0.status', 'http_failure'));

        $this->actingInWorkspace($owner, $workspace)->patch(route('short-links.update', $link), [
            'destination_url' => 'https://example.com/new',
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'is_enabled' => true,
        ])->assertSessionHasNoErrors();

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('links.index'))
            ->assertInertia(fn (Assert $page) => $page->where('destinationHealth', []))
            ->assertInertia(fn (Assert $page) => $page->where('navigation.destination_issues_count', 0));

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('destination-issues.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('issues.open', 0));
    }

    public function test_instance_admin_can_enable_destination_monitoring(): void
    {
        $settings = $this->app->make(InstanceSettings::class);
        $admin = User::factory()->create(['is_instance_admin' => true]);
        $workspace = $this->workspaceFor($admin);
        $data = [
            ...$settings->all(),
            'reserved_slugs' => implode("\n", $settings->get('reserved_slugs')),
            'reserved_prefixes' => implode("\n", $settings->get('reserved_prefixes')),
            'destination_monitoring' => true,
        ];

        $this->actingInWorkspace($admin, $workspace)->patch(route('instance-settings.update'), $data)->assertSessionHasNoErrors();

        $this->assertTrue($settings->get('destination_monitoring'));
    }

    private function incident($openedAt): array
    {
        return [
            'status' => 'http_failure',
            'http_status' => 404,
            'consecutive_failures' => 2,
            'checked_at' => now(),
            'failing_since' => $openedAt,
            'incident_opened_at' => $openedAt,
        ];
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
