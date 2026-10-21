<?php

namespace Tests\Feature\Analytics;

use App\Actions\Analytics\QueueWorkspaceSummaries;
use App\Jobs\SendWorkspaceSummary;
use App\Mail\WorkspaceSummaryMail;
use App\Models\AnalyticsSummaryDelivery;
use App\Models\AnalyticsSummarySchedule;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class WorkspaceSummaryTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_owner_and_admin_configure_the_schedule(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingInWorkspace($admin, $workspace)
            ->withHeader('Host', 'localhost')
            ->put(route('workspace-summary.update'), [
                'is_enabled' => true,
                'timezone' => 'Europe/Paris',
                'recipients' => [$this->membershipOf($workspace, $owner)->id, $this->membershipOf($workspace, $viewer)->id],
            ])
            ->assertRedirect(route('settings.summaries'));

        $schedule = AnalyticsSummarySchedule::query()->sole();
        $this->assertTrue($schedule->is_enabled);
        $this->assertSame('Europe/Paris', $schedule->timezone);
        $this->assertCount(2, $schedule->recipients);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('settings.summaries'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/WorkspaceSummary')
                ->where('schedule.is_enabled', true)
                ->where('schedule.timezone', 'Europe/Paris')
                ->has('schedule.recipients', 2)
                ->has('members', 3)
                ->where('status.last_sent_at', null)
                ->has('status.next_send_at'));
    }

    public function test_editors_and_viewers_cannot_manage_the_schedule(): void
    {
        [$workspace] = $this->workspaceWithActiveDomain();

        foreach ([WorkspaceMember::ROLE_EDITOR, WorkspaceMember::ROLE_VIEWER] as $role) {
            $member = $this->memberOf($workspace, $role);

            $this->actingInWorkspace($member, $workspace)
                ->withHeader('Host', 'localhost')
                ->get(route('settings.summaries'))
                ->assertForbidden();

            $this->actingInWorkspace($member, $workspace)
                ->withHeader('Host', 'localhost')
                ->put(route('workspace-summary.update'), ['is_enabled' => true, 'timezone' => 'UTC', 'recipients' => [$this->membershipOf($workspace, $member)->id]])
                ->assertForbidden();
        }

        $this->assertSame(0, AnalyticsSummarySchedule::query()->count());
    }

    public function test_recipients_must_be_current_members_and_timezone_valid(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        [$other, $stranger] = $this->workspaceWithOwner('Other');

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->put(route('workspace-summary.update'), [
                'is_enabled' => true,
                'timezone' => 'Mars/Olympus',
                'recipients' => [$this->membershipOf($other, $stranger)->id],
            ])
            ->assertSessionHasErrors(['timezone', 'recipients.0']);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->put(route('workspace-summary.update'), ['is_enabled' => true, 'timezone' => 'UTC', 'recipients' => []])
            ->assertSessionHasErrors(['recipients']);

        $this->assertSame(0, AnalyticsSummarySchedule::query()->count());
    }

    public function test_summary_is_queued_once_per_local_week_despite_overlapping_runs(): void
    {
        Queue::fake();
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $schedule = $this->schedule($workspace, [$owner], 'Europe/Paris');

        $this->travelTo(CarbonImmutable::parse('2026-10-26 07:05', 'UTC'));
        $this->artisan('openlink:send-workspace-summaries')->assertSuccessful();
        $this->artisan('openlink:send-workspace-summaries')->assertSuccessful();
        $this->assertSame(0, app(QueueWorkspaceSummaries::class)->handle($schedule->fresh(), CarbonImmutable::now()));

        $delivery = AnalyticsSummaryDelivery::query()->sole();
        $this->assertSame('2026-10-19', $delivery->period_date->toDateString());
        $this->assertSame(169.0, $delivery->period_start->diffInHours($delivery->period_end));
        Queue::assertPushed(SendWorkspaceSummary::class, 1);

        $schedule->update(['timezone' => 'Europe/London']);
        $this->artisan('openlink:send-workspace-summaries')->assertSuccessful();
        $this->assertSame(1, AnalyticsSummaryDelivery::query()->count());
    }

    public function test_summary_is_not_sent_before_send_time_or_after_the_due_window(): void
    {
        Queue::fake();
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $this->schedule($workspace, [$owner], 'America/New_York');

        $this->travelTo(CarbonImmutable::parse('2026-10-19 11:59', 'UTC'));
        $this->artisan('openlink:send-workspace-summaries');
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'UTC'));
        $this->artisan('openlink:send-workspace-summaries');

        $this->assertSame(0, AnalyticsSummaryDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_disabled_schedule_sends_nothing(): void
    {
        Queue::fake();
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $this->schedule($workspace, [$owner], 'UTC')->update(['is_enabled' => false]);

        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        $this->artisan('openlink:send-workspace-summaries');

        Queue::assertNothingPushed();
    }

    public function test_delivery_sends_aggregate_summary_with_authenticated_link(): void
    {
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(3), 'visitor_hash' => 'secret-hash', 'country' => 'FR']);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(3), 'metric' => 'scan']);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(10)]);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(10)]);
        $this->schedule($workspace, [$owner], 'UTC');

        $this->artisan('openlink:send-workspace-summaries');

        $delivery = AnalyticsSummaryDelivery::query()->sole();
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(1, $delivery->attempts);

        Mail::assertSent(WorkspaceSummaryMail::class, function (WorkspaceSummaryMail $mail) use ($owner, $workspace) {
            $html = $mail->render();

            $this->assertSame('Events weekly summary: Oct 12 – Oct 18, 2026', $mail->envelope()->subject);
            $this->assertSame(1, $mail->summary['metrics'][0]['value']);
            $this->assertSame(-50.0, $mail->summary['metrics'][0]['change']);
            $this->assertSame(1, $mail->summary['metrics'][1]['value']);
            $this->assertSame('https://localhost/launch', $mail->summary['top_links'][0]['label']);
            $this->assertStringContainsString('workspace='.$workspace->id, $mail->summary['report_url']);
            $this->assertStringContainsString('/analytics?', $mail->summary['report_url']);
            $this->assertStringNotContainsString('secret-hash', $html);
            $this->assertStringNotContainsString('FR', $html);
            $this->assertStringNotContainsString('token', $mail->summary['report_url']);

            return $mail->hasTo($owner->email);
        });

        $this->get(route('analytics.index', ['workspace' => $workspace->id]))
            ->assertRedirect(route('login'));
    }

    public function test_empty_period_and_retention_are_represented(): void
    {
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        app(InstanceSettings::class)->set('analytics_retention_days', 10);
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $this->schedule($workspace, [$owner], 'UTC');

        $this->artisan('openlink:send-workspace-summaries');

        Mail::assertSent(WorkspaceSummaryMail::class, function (WorkspaceSummaryMail $mail) {
            $html = $mail->render();

            $this->assertTrue($mail->summary['is_empty']);
            $this->assertFalse($mail->summary['is_comparable']);
            $this->assertNull($mail->summary['metrics'][0]['change']);
            $this->assertStringContainsString('No visits or scans were recorded this week.', $html);
            $this->assertStringContainsString('outside the analytics retention window', $html);

            return true;
        });
    }

    public function test_removed_member_and_deselected_recipient_are_cancelled_at_send_time(): void
    {
        Mail::fake();
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $leaving = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $deselected = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $schedule = $this->schedule($workspace, [$owner, $leaving, $deselected], 'UTC');

        $this->artisan('openlink:send-workspace-summaries');
        $this->assertSame(3, AnalyticsSummaryDelivery::query()->count());

        $this->membershipOf($workspace, $leaving)->delete();
        $schedule->recipients()->detach($this->membershipOf($workspace, $deselected)->id);

        AnalyticsSummaryDelivery::query()->pluck('id')->each(fn (int $id) => app()->call([new SendWorkspaceSummary($id), 'handle']));

        Mail::assertSent(WorkspaceSummaryMail::class, 1);
        Mail::assertSent(WorkspaceSummaryMail::class, fn ($mail) => $mail->hasTo($owner->email));
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_CANCELLED, $this->deliveryFor($leaving->id)->status);
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_CANCELLED, $this->deliveryFor($deselected->id)->status);
    }

    public function test_deselecting_or_disabling_cancels_pending_deliveries(): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $this->schedule($workspace, [$owner, $viewer], 'UTC');
        $this->artisan('openlink:send-workspace-summaries');

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->put(route('workspace-summary.update'), ['is_enabled' => true, 'timezone' => 'UTC', 'recipients' => [$this->membershipOf($workspace, $owner)->id]]);

        $this->assertSame(AnalyticsSummaryDelivery::STATUS_CANCELLED, $this->deliveryFor($viewer->id)->status);
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_PENDING, $this->deliveryFor($owner->id)->status);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->put(route('workspace-summary.update'), ['is_enabled' => false, 'timezone' => 'UTC', 'recipients' => []]);

        $this->assertSame(AnalyticsSummaryDelivery::STATUS_CANCELLED, $this->deliveryFor($owner->id)->status);
    }

    public function test_mail_failure_is_recorded_sanitized_and_retry_does_not_duplicate(): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'still-works');
        $this->schedule($workspace, [$owner], 'UTC');
        $this->artisan('openlink:send-workspace-summaries');
        $delivery = AnalyticsSummaryDelivery::query()->sole();

        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Authentication failed for smtp-user:hunter2');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);

        try {
            app()->call([new SendWorkspaceSummary($delivery->id), 'handle']);
            $this->fail('Expected the transport failure to be rethrown for queue retries.');
        } catch (TransportException) {
        }

        $delivery->refresh();
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(SendWorkspaceSummary::MAIL_FAILURE, $delivery->failure);
        $this->assertStringNotContainsString('hunter2', $delivery->failure);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('settings.summaries'))
            ->assertInertia(fn ($page) => $page
                ->where('status.last_failure.message', SendWorkspaceSummary::MAIL_FAILURE)
                ->where('status.last_sent_at', null));

        $this->actingInWorkspace($owner, $workspace)->withHeader('Host', 'localhost')->get(route('analytics.index'))->assertOk();
        $this->withHeader('Host', 'localhost')->get('/still-works')->assertRedirect($link->destination_url);

        Mail::fake();
        app()->call([new SendWorkspaceSummary($delivery->id), 'handle']);
        app()->call([new SendWorkspaceSummary($delivery->id), 'handle']);

        Mail::assertSent(WorkspaceSummaryMail::class, 1);
        $delivery->refresh();
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(2, $delivery->attempts);

        $this->artisan('openlink:send-workspace-summaries');
        $this->assertSame(1, AnalyticsSummaryDelivery::query()->count());
    }

    public function test_permanent_job_failure_and_queue_outage_are_visible(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:00', 'UTC'));
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $this->schedule($workspace, [$owner], 'UTC');

        Queue::shouldReceive('connection')->andThrow(new RuntimeException('redis://:password@cache down'));
        Queue::shouldReceive('push')->andThrow(new RuntimeException('redis://:password@cache down'));
        $this->artisan('openlink:send-workspace-summaries')->assertSuccessful();

        $delivery = AnalyticsSummaryDelivery::query()->sole();
        $this->assertSame(AnalyticsSummaryDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(QueueWorkspaceSummaries::QUEUE_FAILURE, $delivery->failure);

        $delivery->update(['status' => AnalyticsSummaryDelivery::STATUS_PENDING, 'failure' => null]);
        (new SendWorkspaceSummary($delivery->id))->failed(new RuntimeException('secret detail'));

        $this->assertSame(SendWorkspaceSummary::GENERIC_FAILURE, $delivery->fresh()->failure);
    }

    private function schedule(Workspace $workspace, array $users, string $timezone): AnalyticsSummarySchedule
    {
        $schedule = AnalyticsSummarySchedule::create(['workspace_id' => $workspace->id, 'is_enabled' => true, 'timezone' => $timezone]);
        $schedule->recipients()->sync(collect($users)->map(fn ($user) => $this->membershipOf($workspace, $user)->id)->all());

        return $schedule;
    }

    private function deliveryFor(int $userId): AnalyticsSummaryDelivery
    {
        return AnalyticsSummaryDelivery::query()->where('user_id', $userId)->sole();
    }
}
