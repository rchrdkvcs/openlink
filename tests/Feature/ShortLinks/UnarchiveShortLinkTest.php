<?php

namespace Tests\Feature\ShortLinks;

use App\Enums\RevisionAction;
use App\Models\WorkspaceMember;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class UnarchiveShortLinkTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_unarchive_preserves_configuration_and_history_and_refreshes_the_lists(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch', [
            'archived_at' => now(),
            'is_enabled' => false,
            'successful_visits' => 7,
            'visit_limit' => 10,
            'activates_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'fallback_url' => 'https://example.com/fallback',
        ]);
        $event = $this->analyticsEvent($link);
        $configuration = $link->refresh()->getRawOriginal();
        $cache = app(ShortUrlCache::class);
        $cache->shortLinkId($domain, $link->slug);
        $archive = route('links.index', ['status' => 'archived']);

        $this->actingInWorkspace($owner, $workspace)
            ->from($archive)
            ->post('/short-links/'.$link->id.'/unarchive')
            ->assertRedirect($archive)
            ->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertNull($link->archived_at);
        $this->assertSame($configuration, array_replace($link->getRawOriginal(), [
            'archived_at' => $configuration['archived_at'],
            'updated_at' => $configuration['updated_at'],
            'revision' => $configuration['revision'],
        ]));
        $this->assertModelExists($event);
        $this->assertFalse(Cache::has($cache->key($domain, $link->slug)));
        $revision = $link->revisions()->latest('number')->firstOrFail();
        $this->assertSame(RevisionAction::Updated, $revision->action);
        $this->assertSame($owner->id, $revision->actor_id);
        $this->assertSame([['field' => 'is_archived', 'before' => true, 'after' => false]], $revision->changes);

        $this->get($archive)->assertInertia(fn (Assert $page) => $page
            ->has('links', 0)
            ->where('navigation.archived_count', 0));
        $this->get(route('links.index'))->assertInertia(fn (Assert $page) => $page
            ->has('links', 1)
            ->where('links.0.id', $link->id)
            ->where('links.0.status', 'disabled'));

        $this->post(route('short-links.store'), [
            'domain_id' => $domain->id,
            'slug' => $link->slug,
            'destination_url' => 'https://example.com/duplicate',
            'is_enabled' => true,
        ])->assertSessionHasErrors('slug');

        $this->post('/short-links/'.$link->id.'/unarchive')->assertSessionHasNoErrors();
        $this->assertSame(2, $link->revisions()->count());
    }

    public function test_unarchive_keeps_lifecycle_restrictions_and_enabled_configuration(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('localhost');
        $this->actingInWorkspace($owner, $workspace);

        foreach ([
            'active' => [[], true],
            'disabled' => [['is_enabled' => false], false],
            'scheduled' => [['activates_at' => now()->addDay()], false],
            'expired' => [['expires_at' => now()->subDay()], false],
            'limited' => [['visit_limit' => 3, 'successful_visits' => 3], false],
        ] as $slug => [$attributes, $resolves]) {
            $link = $this->shortLink($workspace, $domain, $slug, ['archived_at' => now(), ...$attributes]);
            $this->get('/'.$slug)->assertStatus(404);

            $this->post('/short-links/'.$link->id.'/unarchive')->assertRedirect()->assertSessionHasNoErrors();

            $this->assertSame($attributes['is_enabled'] ?? true, $link->fresh()->is_enabled);
            $response = $this->get('/'.$slug);
            if ($resolves) {
                $response->assertRedirect($link->destination_url);
            } elseif ($slug === 'scheduled') {
                $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Scheduled'));
            } else {
                $response->assertStatus(404);
            }
        }
    }

    public function test_only_content_roles_can_unarchive(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $link = $this->shortLink($workspace, $domain, 'private', ['archived_at' => now()]);

        $this->actingInWorkspace($viewer, $workspace)->post('/short-links/'.$link->id.'/unarchive')->assertForbidden();
        $this->assertNotNull($link->fresh()->archived_at);
        $this->assertSame(0, $link->revisions()->count());

        foreach ([$owner, $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN), $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR)] as $user) {
            $link->update(['archived_at' => now()]);
            $this->actingInWorkspace($user, $workspace)->post('/short-links/'.$link->id.'/unarchive')->assertRedirect();
            $this->assertNull($link->fresh()->archived_at);
        }
    }

    public function test_unarchive_rejects_another_workspace_even_when_the_user_can_edit_both(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $otherWorkspace = $this->workspaceFor($owner, 'Other');
        $link = $this->shortLink($workspace, $domain, 'isolated', ['archived_at' => now()]);

        $this->actingInWorkspace($owner, $otherWorkspace)->post('/short-links/'.$link->id.'/unarchive')->assertForbidden();
        $this->assertNotNull($link->fresh()->archived_at);
        $this->assertSame(0, $link->revisions()->count());
    }
}
