<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsView;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\Tag;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class AnalyticsViewTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_viewer_saves_a_view_with_validated_filter_definition(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $response = $this->actingInWorkspace($viewer, $workspace)
            ->withHeader('Host', 'localhost')
            ->post(route('analytics-views.store'), [
                'name' => 'Launch week',
                'filters' => ['range' => '7d', 'from' => '2026-01-01', 'link' => $link->id, 'metric' => 'visit'],
            ]);

        $view = AnalyticsView::query()->sole();
        $response->assertRedirect(route('analytics.index', ['view' => $view->id]));
        $this->assertSame($viewer->id, $view->user_id);
        $this->assertSame(['range' => '7d', 'link' => $link->id, 'metric' => 'visit'], $view->filters);
    }

    public function test_saving_rejects_foreign_workspace_targets(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        [$other, $otherOwner] = $this->workspaceWithOwner('Other');
        $otherDomain = $this->createDomainFor($other, 'other.test');
        $foreignLink = $this->shortLink($other, $otherDomain, 'foreign');
        $foreignFolder = Folder::create(['workspace_id' => $other->id, 'name' => 'Theirs']);
        $foreignTag = Tag::create(['workspace_id' => $other->id, 'name' => 'theirs']);
        $foreignRule = RoutingRule::create(['short_link_id' => $foreignLink->id, 'name' => 'Mobile']);
        $foreignVariant = RoutingVariant::create(['routing_rule_id' => $foreignRule->id, 'name' => 'B', 'destination_url' => 'https://example.com/b']);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->post(route('analytics-views.store'), [
                'name' => 'Leak',
                'filters' => [
                    'range' => '30d',
                    'link' => $foreignLink->id,
                    'domain' => $otherDomain->id,
                    'folder' => $foreignFolder->id,
                    'tag' => $foreignTag->id,
                    'rule' => $foreignRule->id,
                    'variant' => $foreignVariant->id,
                    'qr' => 999999,
                ],
            ])
            ->assertSessionHasErrors(['filters.link', 'filters.domain', 'filters.folder', 'filters.tag', 'filters.rule', 'filters.variant', 'filters.qr']);

        $this->assertSame(0, AnalyticsView::query()->count());
    }

    public function test_saving_validates_custom_dates(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->post(route('analytics-views.store'), [
                'name' => 'Broken',
                'filters' => ['range' => 'custom', 'from' => '2026-05-10', 'to' => 'soon'],
            ])
            ->assertSessionHasErrors(['filters.to']);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->post(route('analytics-views.store'), [
                'name' => '',
                'filters' => ['range' => 'forever'],
            ])
            ->assertSessionHasErrors(['name', 'filters.range']);
    }

    public function test_rolling_range_advances_when_reopened(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 1)->setTime(12, 0));
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'rolling');
        $view = AnalyticsView::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'name' => 'Last week', 'filters' => ['range' => '7d']]);
        $this->analyticsEvent($link, ['occurred_at' => now()->addDays(20)]);

        $this->travel(21)->days();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['view' => $view->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activeView', ['id' => $view->id, 'name' => 'Last week'])
                ->where('report.range.preset', '7d')
                ->where('report.range.to', now()->toIso8601String())
                ->where('report.summary.visits', 1)
                ->where('unavailableFilters', []));
    }

    public function test_custom_range_stays_fixed_when_reopened(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'fixed');
        $this->analyticsEvent($link, ['occurred_at' => now()->subDays(10)]);
        $this->analyticsEvent($link, ['occurred_at' => now()->subDay()]);
        $view = AnalyticsView::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Campaign',
            'filters' => ['range' => 'custom', 'from' => now()->subDays(12)->toDateString(), 'to' => now()->subDays(8)->toDateString()],
        ]);

        $this->travel(30)->days();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['view' => $view->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.range', 'custom')
                ->where('filters.from', $view->filters['from'])
                ->where('filters.to', $view->filters['to'])
                ->where('report.summary.visits', 1));
    }

    public function test_deleted_target_shows_unavailable_state_instead_of_unfiltered_report(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $kept = $this->shortLink($workspace, $domain, 'kept');
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring']);
        $this->analyticsEvent($kept);
        $view = AnalyticsView::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'name' => 'Spring', 'filters' => ['range' => '30d', 'folder' => $folder->id]]);
        $folder->delete();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['view' => $view->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unavailableFilters', ['folder'])
                ->where('filters.folder', $folder->id)
                ->where('report.summary.visits', 0)
                ->has('report.top_links', 0));
    }

    public function test_target_moved_to_another_workspace_is_unavailable(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        [$other] = $this->workspaceWithOwner('Other');
        $link = $this->shortLink($workspace, $domain, 'moving');
        $this->analyticsEvent($link);
        $view = AnalyticsView::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'name' => 'Domain', 'filters' => ['range' => '30d', 'domain' => $domain->id]]);
        $domain->update(['workspace_id' => $other->id]);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['view' => $view->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unavailableFilters', ['domain'])
                ->where('report.summary.visits', 0));
    }

    public function test_foreign_ids_in_the_query_produce_unavailable_state_and_empty_export(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'mine');
        $this->analyticsEvent($link);
        [$other] = $this->workspaceWithOwner('Other');
        $foreignTag = Tag::create(['workspace_id' => $other->id, 'name' => 'theirs']);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['tag' => $foreignTag->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unavailableFilters', ['tag'])
                ->where('report.summary.visits', 0));

        $csv = $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.export', ['tag' => $foreignTag->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertCount(1, array_filter(explode("\n", trim($csv))));
    }

    public function test_views_are_personal(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $view = AnalyticsView::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'name' => 'Mine', 'filters' => ['range' => '30d']]);

        $this->actingInWorkspace($editor, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index'))
            ->assertInertia(fn ($page) => $page->has('views', 0));

        $this->actingInWorkspace($editor, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['view' => $view->id]))
            ->assertNotFound();

        $this->actingInWorkspace($editor, $workspace)
            ->withHeader('Host', 'localhost')
            ->patch(route('analytics-views.update', $view), ['name' => 'Taken'])
            ->assertForbidden();

        $this->actingInWorkspace($editor, $workspace)
            ->withHeader('Host', 'localhost')
            ->delete(route('analytics-views.destroy', $view))
            ->assertForbidden();

        $this->assertSame('Mine', $view->fresh()->name);
    }

    public function test_views_from_another_workspace_cannot_be_opened_or_changed(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $other = $this->workspaceFor($owner, 'Second');
        $view = AnalyticsView::create(['workspace_id' => $other->id, 'user_id' => $owner->id, 'name' => 'Elsewhere', 'filters' => ['range' => '30d']]);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['view' => $view->id]))
            ->assertNotFound();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->patch(route('analytics-views.update', $view), ['name' => 'Moved'])
            ->assertNotFound();
    }

    public function test_owner_renames_and_deletes_a_view(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $view = AnalyticsView::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'name' => 'Old', 'filters' => ['range' => '90d', 'metric' => 'scan']]);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->patch(route('analytics-views.update', $view), ['name' => 'Quarter scans'])
            ->assertRedirect(route('analytics.index', ['view' => $view->id]));

        $this->assertSame('Quarter scans', $view->fresh()->name);
        $this->assertSame(['range' => '90d', 'metric' => 'scan'], $view->fresh()->filters);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->delete(route('analytics-views.destroy', $view))
            ->assertRedirect(route('analytics.index', ['range' => '90d', 'metric' => 'scan']));

        $this->assertModelMissing($view);
    }

    public function test_signed_report_link_switches_to_a_member_workspace_only(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        $second = $this->workspaceFor($owner, 'Second');
        [$foreign] = $this->workspaceWithOwner('Foreign');

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(URL::signedRoute('analytics.open', ['workspace' => $second->id, 'range' => '7d']))
            ->assertRedirect(route('analytics.index', ['range' => '7d']))
            ->assertSessionHas('workspace_id', $second->id);

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.open', ['workspace' => $second->id]))
            ->assertForbidden();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(URL::signedRoute('analytics.open', ['workspace' => $foreign->id]))
            ->assertForbidden();

        $this->actingInWorkspace($owner, $workspace)
            ->withHeader('Host', 'localhost')
            ->get(route('analytics.index', ['workspace' => $second->id]))
            ->assertOk()
            ->assertSessionHas('workspace_id', $workspace->id);
    }

    private function createDomainFor(Workspace $workspace, string $hostname): Domain
    {
        return Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'token-'.$hostname,
            'verified_at' => now(),
        ]);
    }
}
