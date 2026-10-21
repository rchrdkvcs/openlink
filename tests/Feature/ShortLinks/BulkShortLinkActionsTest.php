<?php

namespace Tests\Feature\ShortLinks;

use App\Enums\LinkStatus;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ShortLinks\ShortLinkLifecycle;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class BulkShortLinkActionsTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    private Workspace $workspace;

    private Domain $domain;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->workspace, $this->domain, $this->owner] = $this->workspaceWithActiveDomain('go.example.test');
    }

    public function test_content_roles_can_move_a_selection_to_a_folder_and_back_to_unfiled(): void
    {
        $folder = Folder::create(['workspace_id' => $this->workspace->id, 'name' => 'Campaigns']);
        $links = $this->links(['one', 'two']);

        foreach ([WorkspaceMember::ROLE_ADMIN, WorkspaceMember::ROLE_EDITOR] as $role) {
            $this->bulk($this->memberOf($this->workspace, $role), 'move', $links, ['folder_id' => $folder->id])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame([$folder->id, $folder->id], $this->fresh($links)->pluck('folder_id')->all());

            $this->bulk($this->owner, 'move', $links, ['folder_id' => null])->assertSessionHasNoErrors();

            $this->assertSame([null, null], $this->fresh($links)->pluck('folder_id')->all());
        }
    }

    public function test_viewer_cannot_mutate_a_selection(): void
    {
        $links = $this->links(['one']);
        $viewer = $this->memberOf($this->workspace, WorkspaceMember::ROLE_VIEWER);

        $this->bulk($viewer, 'disable', $links)->assertForbidden();

        $this->assertTrue($links[0]->fresh()->is_enabled);
    }

    public function test_a_selection_with_a_foreign_workspace_link_is_rejected_before_any_write(): void
    {
        $links = $this->links(['mine']);
        [$otherWorkspace, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->shortLink($otherWorkspace, $otherDomain, 'theirs');

        $this->bulk($this->owner, 'disable', [...$links, $foreign])
            ->assertSessionHasErrors('ids');

        $this->assertTrue($links[0]->fresh()->is_enabled);
        $this->assertTrue($foreign->fresh()->is_enabled);
    }

    public function test_a_stale_selection_is_rejected_without_applying_a_partial_batch(): void
    {
        $links = $this->links(['kept', 'gone']);
        $staleIds = [$links[0]->id, $links[1]->id];
        $links[1]->delete();

        $this->actingInWorkspace($this->owner, $this->workspace)
            ->post(route('short-links.bulk'), ['action' => 'archive', 'ids' => $staleIds])
            ->assertSessionHasErrors(['ids' => '1 selected link is no longer available. Refresh the list and try again.']);

        $this->assertNull($links[0]->fresh()->archived_at);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $links = $this->links(['one']);
        $foreignFolder = Folder::create([
            'workspace_id' => $this->workspaceWithOwner('Other')[0]->id,
            'name' => 'Theirs',
        ]);

        $this->bulk($this->owner, 'delete', $links)->assertSessionHasErrors('action');
        $this->bulk($this->owner, 'move', $links, ['folder_id' => $foreignFolder->id])->assertSessionHasErrors('folder_id');
        $this->bulk($this->owner, 'add_tags', $links, ['tags' => []])->assertSessionHasErrors('tags');
        $this->bulk($this->owner, 'disable', [])->assertSessionHasErrors('ids');

        $this->assertNull($links[0]->fresh()->folder_id);
    }

    public function test_batch_size_is_bounded(): void
    {
        $ids = range(1, 101);

        $this->actingInWorkspace($this->owner, $this->workspace)
            ->post(route('short-links.bulk'), ['action' => 'disable', 'ids' => $ids])
            ->assertSessionHasErrors('ids');
    }

    public function test_a_failure_mid_batch_rolls_back_every_change(): void
    {
        $links = $this->links(['first', 'second']);
        ShortLink::saving(function (ShortLink $link): void {
            if ($link->slug === 'second' && ! $link->is_enabled) {
                throw new \RuntimeException('Write failed');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->bulk($this->owner, 'disable', $links);
            $this->fail('The batch should have failed.');
        } catch (\RuntimeException) {
        }

        $this->assertSame([true, true], $this->fresh($links)->pluck('is_enabled')->all());
    }

    public function test_tags_can_be_added_and_removed_without_touching_other_tags(): void
    {
        $links = $this->links(['one', 'two']);
        $keep = Tag::create(['workspace_id' => $this->workspace->id, 'name' => 'keep']);
        $links[0]->tags()->attach($keep);

        $this->bulk($this->owner, 'add_tags', $links, ['tags' => ['launch', 'keep']])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['keep', 'launch'], $links[0]->tags()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['keep', 'launch'], $links[1]->tags()->pluck('name')->all());

        $this->bulk($this->owner, 'remove_tags', $links, ['tags' => ['launch', 'unknown']])->assertSessionHasNoErrors();

        $this->assertSame(['keep'], $links[0]->tags()->pluck('name')->all());
        $this->assertSame(['keep'], $links[1]->tags()->pluck('name')->all());
        $this->assertDatabaseMissing('tags', ['name' => 'unknown']);
        $this->assertSame(1, Tag::query()->where('name', 'launch')->where('workspace_id', $this->workspace->id)->count());
    }

    public function test_links_can_be_disabled_and_re_enabled_without_overriding_lifecycle_rules(): void
    {
        $links = $this->links(['one']);
        $expired = $this->shortLink($this->workspace, $this->domain, 'expired', ['expires_at' => now()->subDay()]);

        $this->bulk($this->owner, 'disable', [...$links, $expired])->assertSessionHasNoErrors();
        $this->assertSame([false, false], $this->fresh([...$links, $expired])->pluck('is_enabled')->all());

        $this->bulk($this->owner, 'enable', [...$links, $expired])->assertSessionHasNoErrors();
        $this->assertSame([true, true], $this->fresh([...$links, $expired])->pluck('is_enabled')->all());
        $this->assertNotNull($expired->fresh()->expires_at);
        $this->assertSame(LinkStatus::Expired, app(ShortLinkLifecycle::class)->statusOf($expired->fresh()));
    }

    public function test_archiving_keeps_slugs_analytics_and_visit_counters(): void
    {
        $links = $this->links(['one', 'two'], ['successful_visits' => 7]);
        $this->analyticsEvent($links[0]);
        $alreadyArchivedAt = now()->subWeek()->startOfSecond();
        $links[1]->update(['archived_at' => $alreadyArchivedAt]);

        $this->bulk($this->owner, 'archive', $links)->assertSessionHasNoErrors();

        $fresh = $this->fresh($links);
        $this->assertNotNull($fresh[0]->archived_at);
        $this->assertTrue($fresh[1]->archived_at->equalTo($alreadyArchivedAt));
        $this->assertSame(['one', 'two'], $fresh->pluck('slug')->all());
        $this->assertSame([7, 7], $fresh->pluck('successful_visits')->all());
        $this->assertSame(1, $links[0]->analyticsEvents()->count());
    }

    public function test_cached_resolutions_are_forgotten_like_single_link_mutations(): void
    {
        $links = $this->links(['one', 'two']);
        $cache = app(ShortUrlCache::class);
        $cache->shortLinkId($this->domain, 'one');
        $cache->shortLinkId($this->domain, 'two');

        $this->bulk($this->owner, 'disable', $links)->assertSessionHasNoErrors();

        $this->assertFalse(Cache::has($cache->key($this->domain, 'one')));
        $this->assertFalse(Cache::has($cache->key($this->domain, 'two')));
    }

    private function links(array $slugs, array $attributes = []): array
    {
        return array_map(fn (string $slug) => $this->shortLink($this->workspace, $this->domain, $slug, [
            'is_enabled' => true,
            ...$attributes,
        ]), $slugs);
    }

    private function fresh(array $links)
    {
        return ShortLink::query()->whereKey(array_map(fn (ShortLink $link) => $link->id, $links))->orderBy('id')->get();
    }

    private function bulk(User $user, string $action, array $links, array $data = [])
    {
        return $this->actingInWorkspace($user, $this->workspace)->post(route('short-links.bulk'), [
            'action' => $action,
            'ids' => array_map(fn (ShortLink $link) => $link->id, $links),
            ...$data,
        ]);
    }
}
