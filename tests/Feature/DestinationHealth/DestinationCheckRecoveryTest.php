<?php

namespace Tests\Feature\DestinationHealth;

use App\Actions\DestinationHealth\DestinationChecks;
use App\Actions\DestinationHealth\DestinationHealthPayload;
use App\Enums\DestinationHealthStatus;
use App\Jobs\CheckDestination;
use App\Models\DestinationCheck;
use App\Services\DestinationHealth\DestinationProbe;
use App\Services\DestinationHealth\DestinationProbeResult;
use App\Services\Dns\DnsLookup;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class DestinationCheckRecoveryTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private int $httpStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        $this->app->instance(DnsLookup::class, (new InMemoryDnsLookup)->pointTo('example.com', '93.184.216.34'));
        Http::fake(fn () => Http::response('', $this->httpStatus));
    }

    public function test_a_lost_queued_job_can_be_replaced_explicitly_without_expiring_a_backlog(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $oldJob = CheckDestination::for($check);
        Queue::connection()->pop()->delete();
        $this->travel(61)->seconds();

        $this->assertSame('queued', $check->fresh()->executionState());
        $this->assertTrue($check->fresh()->isPending());
        $this->assertTrue($check->fresh()->canRetry());
        $this->post(route('short-links.destination-checks.store', $link))->assertSessionHasNoErrors();

        $this->assertNotSame($oldJob->generation, $check->fresh()->generation);
        $oldJob->handle(app(DestinationProbe::class), app(DestinationChecks::class));
        $oldJob->failed(new RuntimeException('late failure containing private data'));
        Http::assertNothingSent();
        $this->assertSame('queued', $check->fresh()->executionState());

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->assertSame('completed', $check->fresh()->executionState());
        $this->assertSame(DestinationHealthStatus::Reachable, $check->fresh()->status);
    }

    public function test_a_killed_worker_loses_its_running_lease_and_a_late_result_cannot_overwrite_a_replacement(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $oldJob = CheckDestination::for($check);
        Queue::connection()->pop()->delete();
        $probe = $this->mock(DestinationProbe::class);
        $probe->shouldReceive('check')->once()->andReturnUsing(function () use ($owner, $check) {
            $this->travel(DestinationCheck::RUNNING_LEASE_SECONDS + 1)->seconds();
            $expired = $check->fresh();
            $payload = app(DestinationHealthPayload::class)->check($expired);
            $this->assertSame('failed', $payload['execution_state']);
            $this->assertFalse($payload['pending']);
            $this->assertTrue($payload['can_retry']);
            app(DestinationChecks::class)->retry($owner, $expired);
            $replacement = $expired->fresh();
            $replacement->update(['execution_state' => 'running', 'started_at' => now()]);
            app(DestinationChecks::class)->record($replacement, new DestinationProbeResult(DestinationHealthStatus::Reachable, 200));

            return new DestinationProbeResult(DestinationHealthStatus::HttpFailure, 404);
        });

        $oldJob->handle($probe, app(DestinationChecks::class));
        $oldJob->failed(new RuntimeException('old timeout'));

        $latest = $check->fresh();
        $this->assertNotSame($oldJob->generation, $latest->generation);
        $this->assertSame('completed', $latest->executionState());
        $this->assertSame(DestinationHealthStatus::Reachable, $latest->status);
        $this->assertSame(200, $latest->http_status);
        $this->assertSame(0, $latest->consecutive_failures);
    }

    public function test_monitoring_recovers_a_lost_queued_request_after_its_full_waiting_window(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'lost');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $lost = DestinationCheck::query()->firstOrFail();
        $oldJob = CheckDestination::for($lost);
        Queue::connection()->pop()->delete();
        $this->travel(33)->minutes();
        $backlog = $this->shortLink($workspace, $domain, 'backlog', ['destination_url' => 'https://example.com/backlog']);
        $this->post(route('short-links.destination-checks.store', $backlog));
        $waiting = DestinationCheck::query()->where('url', $backlog->destination_url)->firstOrFail();
        $waiting->update(['requested_at' => now()->subMinutes(11)]);
        app(InstanceSettings::class)->set('destination_monitoring', true);

        $this->artisan('openlink:monitor-destinations', ['--limit' => 1])->assertSuccessful();

        $this->assertNotSame($oldJob->generation, $lost->fresh()->generation);
        $this->assertSame($waiting->generation, $waiting->fresh()->generation);
        $this->assertDatabaseCount('jobs', 2);
        $oldJob->handle(app(DestinationProbe::class), app(DestinationChecks::class));
        $oldJob->failed(new RuntimeException('abandoned request'));
        Http::assertNothingSent();
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true])->assertSuccessful();
        $this->assertSame('completed', $lost->fresh()->executionState());
    }

    public function test_monitoring_recovers_an_expired_running_request_even_with_a_recent_health_result(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'lost');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $lost = DestinationCheck::query()->firstOrFail();
        $oldJob = CheckDestination::for($lost);
        Queue::connection()->pop()->delete();
        $lost->update([
            'execution_state' => 'running', 'started_at' => now()->subSeconds(121),
            'status' => 'reachable', 'http_status' => 200, 'checked_at' => now()->subMinutes(5),
        ]);
        app(InstanceSettings::class)->set('destination_monitoring', true);

        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        $this->assertNotSame($oldJob->generation, $lost->fresh()->generation);
        $this->assertSame('queued', $lost->fresh()->execution_state);
        $this->assertSame(DestinationHealthStatus::Reachable, $lost->fresh()->status);
        $oldJob->failed(new RuntimeException('late worker timeout'));
        $this->assertSame('queued', $lost->fresh()->execution_state);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_a_completed_job_can_be_redelivered_without_probing_or_counting_a_second_failure(): void
    {
        $this->httpStatus = 404;
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $job = CheckDestination::for($check);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $recorded = $check->fresh()->getAttributes();
        Http::assertSentCount(2);
        $this->assertSame(1, $check->fresh()->consecutive_failures);
        $this->travel(1)->minute();

        $job->handle(app(DestinationProbe::class), app(DestinationChecks::class));
        $job->failed(new RuntimeException('duplicate delivery failed'));

        Http::assertSentCount(2);
        $this->assertSame($recorded, $check->fresh()->getAttributes());
        $this->assertSame(1, $check->fresh()->consecutive_failures);
        $this->assertFalse($check->fresh()->hasIncident());
    }

    public function test_a_failed_job_is_not_restarted_by_redelivery(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $job = CheckDestination::for($check);
        $job->failed(new RuntimeException('terminal failure'));
        $recorded = $check->fresh()->getAttributes();

        $job->handle(app(DestinationProbe::class), app(DestinationChecks::class));

        Http::assertNothingSent();
        $this->assertSame($recorded, $check->fresh()->getAttributes());
    }

    public function test_redelivery_waits_for_an_active_running_lease(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $check->update(['execution_state' => 'running', 'started_at' => now()]);
        $job = CheckDestination::for($check)->withFakeQueueInteractions();
        $recorded = $check->getAttributes();

        $job->handle(app(DestinationProbe::class), app(DestinationChecks::class));

        $job->assertReleased();
        Http::assertNothingSent();
        $this->assertSame($recorded, $check->fresh()->getAttributes());
    }

    public function test_expired_running_redelivery_replaces_the_generation_before_probing(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $check = DestinationCheck::query()->firstOrFail();
        $job = CheckDestination::for($check);
        Queue::connection()->pop()->delete();
        $check->update(['execution_state' => 'running', 'started_at' => now()->subSeconds(121)]);

        $job->handle(app(DestinationProbe::class), app(DestinationChecks::class));
        $job->failed(new RuntimeException('late failure of expired attempt'));

        Http::assertNothingSent();
        $this->assertNotSame($job->generation, $check->fresh()->generation);
        $this->assertSame('queued', $check->fresh()->execution_state);
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->assertSame('completed', $check->fresh()->executionState());
    }

    public function test_recovery_cannot_replace_a_generation_whose_worker_has_just_started(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->post(route('short-links.destination-checks.store', $link));
        $stale = DestinationCheck::query()->firstOrFail();
        $stale->update(['requested_at' => now()->subMinutes(33)]);
        $running = $stale->fresh();
        $running->update(['execution_state' => 'running', 'started_at' => now()]);

        app(DestinationChecks::class)->queue($stale);

        $this->assertSame($running->generation, $running->fresh()->generation);
        $this->assertSame('running', $running->fresh()->execution_state);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_monitoring_recovers_legacy_pending_rows_but_preserves_active_running_leases(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        foreach ([null, 'queued', 'running'] as $index => $state) {
            $link = $this->shortLink($workspace, $domain, "legacy-$index", ['destination_url' => "https://example.com/$index"]);
            DestinationCheck::query()->create([
                'workspace_id' => $workspace->id, 'url' => $link->destination_url,
                'url_hash' => DestinationCheck::hashFor($link->destination_url),
                'execution_state' => $state, 'requested_at' => now()->subMinutes(33),
                'started_at' => $state === 'running' ? now() : null,
                'checked_at' => now()->subMinutes(34), 'status' => 'inconclusive', 'http_status' => 403,
            ]);
        }
        app(InstanceSettings::class)->set('destination_monitoring', true);

        $this->artisan('openlink:monitor-destinations')->assertSuccessful();

        $this->assertSame(2, DestinationCheck::query()->whereNotNull('generation')->count());
        $this->assertSame(3, DestinationCheck::query()->where('status', 'inconclusive')->count());
        $this->assertSame(1, DestinationCheck::query()->where('execution_state', 'running')->whereNull('generation')->count());
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_legacy_pending_rows_without_a_job_can_be_retried_after_upgrade(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $migration = require database_path('migrations/2026_10_22_000105_track_destination_check_execution.php');
        $migration->down();
        $check = DestinationCheck::query()->create([
            'workspace_id' => $workspace->id,
            'url' => $link->destination_url,
            'url_hash' => DestinationCheck::hashFor($link->destination_url),
            'requested_at' => now()->subDay(),
            'status' => 'inconclusive',
            'http_status' => 403,
            'checked_at' => now()->subDays(2),
        ]);
        $migration->up();
        $this->assertSame('queued', $check->fresh()->executionState());
        $this->assertNull($check->fresh()->generation);
        $this->assertSame(DestinationHealthStatus::Inconclusive, $check->fresh()->status);
        $legacyJob = unserialize(sprintf(
            'O:%d:"%s":2:{s:18:"destinationCheckId";i:%d;s:4:"host";s:11:"example.com";}',
            strlen(CheckDestination::class), CheckDestination::class, $check->id,
        ));

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.destination-checks.store', $link))->assertSessionHasNoErrors();
        $legacyJob->handle(app(DestinationProbe::class), app(DestinationChecks::class));
        $legacyJob->failed(new RuntimeException('legacy job failed'));

        Http::assertNothingSent();
        $this->assertNotNull($check->fresh()->generation);
        $this->assertSame('queued', $check->fresh()->executionState());
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
        $this->assertSame('completed', $check->fresh()->executionState());
    }
}
