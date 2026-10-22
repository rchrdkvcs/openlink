<?php

namespace Tests\Feature\DestinationHealth;

use App\Jobs\CheckDestination;
use App\Models\DestinationCheck;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class DestinationHealthPagesTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

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
