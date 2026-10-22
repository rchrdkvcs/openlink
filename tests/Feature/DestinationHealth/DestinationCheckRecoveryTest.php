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

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        $this->app->instance(DnsLookup::class, (new InMemoryDnsLookup)->pointTo('example.com', '93.184.216.34'));
        Http::fake(fn () => Http::response('', 200));
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
