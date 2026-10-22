<?php

namespace Tests\Feature\Diagnostics;

use App\Models\DiagnosticObservation;
use App\Models\User;
use App\Services\Diagnostics\Heartbeats;
use App\Services\Diagnostics\Probes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class InstanceDiagnosticsReportTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_instance_admin' => true]);
        $this->workspaceFor($this->admin);
        config(['queue.default' => 'redis', 'openlink.analytics.via_queue' => true]);
        $this->actingAs($this->admin)->withHeader('Host', 'localhost');
    }

    public function test_scheduler_heartbeat_is_missing_until_the_scheduled_command_runs_and_fresh_afterwards(): void
    {
        $this->assertCheck('scheduler', 'missing');

        Artisan::call('openlink:heartbeat');

        $this->assertCheck('scheduler', 'healthy');
    }

    public function test_heartbeat_turns_stale_after_three_configured_cadences(): void
    {
        $this->freezeSecond();
        config(['openlink.diagnostics.heartbeat_minutes' => 5]);
        Artisan::call('openlink:heartbeat');

        $this->travel(15)->minutes();
        $this->assertCheck('scheduler', 'healthy');

        $this->travel(1)->minutes();
        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.scheduler.state', 'stale')
                ->where('checks.scheduler.observedAt', fn (string $at) => now()->diffInMinutes($at, true) >= 16)
                ->where('heartbeat.cadenceMinutes', 5)
                ->where('heartbeat.staleAfterMinutes', 15));
    }

    public function test_scheduler_runs_the_heartbeat_on_the_configured_cadence(): void
    {
        $this->assertSame('* * * * *', app(Heartbeats::class)->schedulerCron());

        config(['openlink.diagnostics.heartbeat_minutes' => 5]);

        $this->assertSame('*/5 * * * *', app(Heartbeats::class)->schedulerCron());
    }

    public function test_worker_heartbeat_comes_from_the_worker_loop_and_is_throttled_to_the_cadence(): void
    {
        $this->assertCheck('worker', 'missing');

        Event::dispatch(new Looping('redis', 'default'));
        $first = DiagnosticObservation::findOrFail(DiagnosticObservation::WORKER)->succeeded_at;

        $this->travel(30)->seconds();
        Event::dispatch(new Looping('redis', 'default'));
        $this->assertTrue($first->equalTo(DiagnosticObservation::findOrFail(DiagnosticObservation::WORKER)->succeeded_at));

        $this->travel(31)->seconds();
        Event::dispatch(new Looping('redis', 'default'));
        $this->assertTrue(DiagnosticObservation::findOrFail(DiagnosticObservation::WORKER)->succeeded_at->greaterThan($first));

        $this->assertCheck('worker', 'healthy');
    }

    public function test_stopped_worker_is_detected_while_redis_is_healthy(): void
    {
        $this->partialMock(Probes::class, fn ($mock) => $mock->shouldReceive('redis')->andReturnNull());
        Event::dispatch(new Looping('redis', 'default'));
        Artisan::call('openlink:heartbeat');

        $this->travel(4)->minutes();
        Artisan::call('openlink:heartbeat');
        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.redis.state', 'healthy')
                ->where('checks.scheduler.state', 'healthy')
                ->where('checks.worker.state', 'stale')
                ->where('checks.worker.hint', fn (string $hint) => str_contains($hint, 'worker')));
    }

    public function test_stopped_scheduler_is_detected_while_the_worker_is_healthy(): void
    {
        Artisan::call('openlink:heartbeat');
        $this->travel(4)->minutes();
        Event::dispatch(new Looping('redis', 'default'));

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.worker.state', 'healthy')
                ->where('checks.scheduler.state', 'stale')
                ->where('checks.scheduler.hint', fn (string $hint) => str_contains($hint, 'scheduler')));
    }

    public function test_sync_queue_reports_the_worker_as_disabled_instead_of_missing(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertCheck('worker', 'disabled');
    }

    public function test_redis_is_disabled_when_nothing_uses_it_and_unavailable_when_unreachable(): void
    {
        config(['cache.default' => 'array', 'queue.default' => 'database', 'session.driver' => 'array']);
        $this->assertCheck('redis', 'disabled');

        config([
            'cache.default' => 'redis',
            'database.redis.client' => 'predis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
            'database.redis.default.password' => 'redis-secret-password',
        ]);
        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->get(route('settings.diagnostics'))
            ->assertDontSee('redis-secret-password')
            ->assertDontSee('127.0.0.1:1')
            ->assertInertia(fn ($page) => $page
                ->where('checks.redis.state', 'unavailable')
                ->where('checks.redis.observedAt', null)
                ->whereNot('checks.redis.checkedAt', null));
    }

    public function test_failed_probes_report_unavailable_without_exposing_exception_details(): void
    {
        $leak = 'SQLSTATE[08006] connection to host db.internal password=hunter2 at /var/www/storage/app';
        $this->partialMock(Probes::class, function ($mock) use ($leak) {
            $mock->shouldReceive('database')->andThrow(new RuntimeException($leak));
            $mock->shouldReceive('storage')->andThrow(new RuntimeException($leak));
        });

        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->get(route('settings.diagnostics'))
            ->assertOk()
            ->assertDontSee('hunter2')
            ->assertDontSee('db.internal')
            ->assertDontSee('/var/www')
            ->assertDontSee('SQLSTATE')
            ->assertInertia(fn ($page) => $page
                ->where('checks.database.state', 'unavailable')
                ->where('checks.storage.state', 'unavailable')
                ->whereNot('checks.database.hint', null));
    }

    public function test_last_successful_observation_survives_a_later_failure(): void
    {
        $this->post(route('instance-diagnostics.recheck'));
        $this->get(route('settings.diagnostics'))->assertInertia(fn ($page) => $page->where('checks.storage.state', 'healthy'));
        $succeededAt = DiagnosticObservation::findOrFail('storage')->succeeded_at;

        $this->travel(10)->minutes();
        $this->partialMock(Probes::class, fn ($mock) => $mock->shouldReceive('storage')->andThrow(new RuntimeException('disk full')));
        $this->post(route('instance-diagnostics.recheck'));

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.storage.state', 'unavailable')
                ->where('checks.storage.observedAt', $succeededAt->toIso8601String())
                ->where('checks.storage.checkedAt', now()->toIso8601String()));
    }

    public function test_rechecks_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('instance-diagnostics.recheck'))->assertRedirect();
        }

        $this->post(route('instance-diagnostics.recheck'))->assertTooManyRequests();
    }

    public function test_after_response_analytics_need_no_worker(): void
    {
        config(['openlink.analytics.via_queue' => false]);

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.analytics.state', 'healthy')
                ->where('checks.analytics.mode', 'after_response')
                ->where('checks.worker.state', 'disabled')
                ->where('checks.worker.hint', null)
                ->where('checks.worker.summary', fn (string $summary) => str_contains($summary, 'none is required')));
    }

    public function test_stale_worker_is_not_required_when_analytics_record_after_the_response(): void
    {
        config(['openlink.analytics.via_queue' => false]);
        Event::dispatch(new Looping('redis', 'default'));
        $this->travel(4)->minutes();

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.worker.state', 'disabled')
                ->whereNot('checks.worker.observedAt', null)
                ->where('checks.worker.summary', fn (string $summary) => str_contains($summary, 'recently')));

        Event::dispatch(new Looping('redis', 'default'));

        $this->assertCheck('worker', 'healthy');
    }

    public function test_sync_jobs_inside_requests_do_not_count_as_a_worker_heartbeat(): void
    {
        Event::dispatch(new Looping('sync', 'default'));

        $this->assertDatabaseMissing('diagnostic_observations', ['name' => DiagnosticObservation::WORKER]);
    }

    public function test_probed_checks_are_missing_until_an_explicit_recheck_and_page_views_never_probe(): void
    {
        $this->partialMock(Probes::class, fn ($mock) => $mock->shouldReceive('database', 'redis', 'storage')->never());

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.database.state', 'missing')
                ->where('checks.storage.state', 'missing')
                ->where('checks.database.checkedAt', null));
    }

    public function test_remote_storage_disks_are_not_probed(): void
    {
        config(['filesystems.default' => 's3', 'filesystems.disks.s3.driver' => 's3']);
        $this->partialMock(Probes::class, fn ($mock) => $mock->shouldReceive('storage')->never());

        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->assertCheck('storage', 'disabled');
    }

    public function test_storage_probe_leaves_no_file_behind(): void
    {
        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->assertCheck('storage', 'healthy');
        $this->assertSame([], array_values(array_filter(
            Storage::disk(config('filesystems.default'))->files(),
            fn (string $file) => str_starts_with($file, '.openlink-diagnostics-'),
        )));
    }

    public function test_unreachable_database_check_finishes_within_its_timeout(): void
    {
        config([
            'database.connections.pgsql.host' => '10.255.255.1',
            'database.connections.pgsql.password' => 'pg-secret-password',
        ]);

        $started = microtime(true);
        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->assertLessThan(Probes::TIMEOUT_SECONDS * 2 + 2, microtime(true) - $started);
        $this->assertSame('unavailable', DiagnosticObservation::findOrFail('database')->state);
        $this->get(route('settings.diagnostics'))
            ->assertDontSee('pg-secret-password')
            ->assertDontSee('10.255.255.1')
            ->assertInertia(fn ($page) => $page->where('checks.database.state', 'unavailable'));
    }

    public function test_queue_mode_analytics_depend_on_a_running_worker(): void
    {
        config(['openlink.analytics.via_queue' => true]);

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.analytics.state', 'unavailable')
                ->where('checks.analytics.mode', 'queue')
                ->where('checks.worker.summary', fn (string $summary) => str_contains($summary, 'Analytics recording in queue mode needs a running worker')));

        Event::dispatch(new Looping('redis', 'default'));

        $this->assertCheck('analytics', 'healthy');
    }

    public function test_queue_mode_on_a_sync_queue_is_not_configured(): void
    {
        config(['openlink.analytics.via_queue' => true, 'queue.default' => 'sync']);

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.analytics.state', 'not_configured')
                ->where('checks.analytics.hint', fn (string $hint) => str_contains($hint, 'QUEUE_CONNECTION')));
    }

    private function assertCheck(string $check, string $state): void
    {
        $this->get(route('settings.diagnostics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where("checks.{$check}.state", $state));
    }

    public function test_public_redirects_never_run_diagnostic_probes(): void
    {
        config(['queue.default' => 'sync', 'openlink.analytics.via_queue' => false]);
        $this->mock(Probes::class, fn ($mock) => $mock->shouldReceive('database', 'redis', 'storage')->never());
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'launch');

        $this->withHeader('Host', 'go.example.test')
            ->get('http://go.example.test/launch')
            ->assertRedirect('https://example.com/landing');

        $this->assertDatabaseMissing('diagnostic_observations', ['name' => 'redis']);
        $this->assertDatabaseMissing('diagnostic_observations', ['name' => 'database']);
    }

    public function test_unreachable_redis_check_finishes_within_its_timeout(): void
    {
        config([
            'cache.default' => 'redis',
            'database.redis.client' => 'predis',
            'database.redis.default.host' => '10.255.255.1',
            'database.redis.default.port' => 6379,
        ]);

        $started = microtime(true);
        $this->post(route('instance-diagnostics.recheck'))->assertRedirect();

        $this->assertLessThan(Probes::TIMEOUT_SECONDS + 2, microtime(true) - $started);
        $this->assertSame('unavailable', DiagnosticObservation::findOrFail('redis')->state);
    }
}
