<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesLinkPages;
use Tests\TestCase;

class ManageLinkPagesTest extends TestCase
{
    use CreatesLinkPages;
    use RefreshDatabase;

    public function test_members_list_only_their_workspace_link_pages(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $this->linkPage($workspace, ['name' => 'Spring']);
        $this->linkPage($otherWorkspace, ['name' => 'Elsewhere', 'path' => 'elsewhere']);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingInWorkspace($viewer, $workspace)
            ->get(route('link-pages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LinkPages/Index')
                ->has('linkPages', 1)
                ->where('linkPages.0.name', 'Spring')
                ->where('linkPages.0.is_published', false)
                ->where('linkPages.0.public_url', 'http://localhost/p/spring'));
    }

    public function test_editor_creates_a_draft_link_page(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);

        $response = $this->actingInWorkspace($editor, $workspace)
            ->post(route('link-pages.store'), ['name' => 'Spring conference', 'path' => 'Spring-2026']);

        $linkPage = LinkPage::query()->firstOrFail();
        $response->assertRedirect(route('link-pages.show', $linkPage));
        $this->assertSame($workspace->id, $linkPage->workspace_id);
        $this->assertSame('spring-2026', $linkPage->path);
        $this->assertSame('Spring conference', $linkPage->title);
        $this->assertFalse($linkPage->isPublished());
    }

    public function test_editor_updates_content_and_orders_selected_short_links(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $first = $this->shortLink($workspace, $domain, 'first');
        $second = $this->shortLink($workspace, $domain, 'second');
        $linkPage = $this->linkPage($workspace, [], [$first, $second]);
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $before = [$first->fresh()->toArray(), $second->fresh()->toArray()];

        $this->actingInWorkspace($editor, $workspace)
            ->patch(route('link-pages.update', $linkPage), [
                'name' => 'Spring',
                'path' => 'spring',
                'title' => 'Spring 2026',
                'description' => 'Two links.',
                'items' => [
                    ['short_link_id' => $second->id, 'label' => 'Second first'],
                    ['short_link_id' => $first->id, 'label' => ''],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $linkPage->refresh();
        $this->assertSame('Spring 2026', $linkPage->title);
        $this->assertSame([$second->id, $first->id], $linkPage->items->pluck('short_link_id')->all());
        $this->assertSame(['Second first', null], $linkPage->items->pluck('label')->all());
        $this->assertSame($before, [$first->fresh()->toArray(), $second->fresh()->toArray()]);
    }

    public function test_editor_publishes_and_unpublishes_without_changing_short_links(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'tickets');
        $linkPage = $this->linkPage($workspace, [], [$link]);
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $before = $link->fresh()->toArray();

        $this->actingInWorkspace($editor, $workspace)->post(route('link-pages.publish', $linkPage))->assertRedirect();
        $this->assertTrue($linkPage->fresh()->isPublished());
        $this->get('/p/spring')->assertOk();

        $this->actingInWorkspace($editor, $workspace)->post(route('link-pages.unpublish', $linkPage))->assertRedirect();
        $this->assertFalse($linkPage->fresh()->isPublished());
        $this->get('/p/spring')->assertNotFound();

        $this->assertSame($before, $link->fresh()->toArray());
    }

    public function test_editor_deletes_a_link_page_without_deleting_short_links(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'tickets');
        $linkPage = $this->publishedLinkPage($workspace, [], [$link]);
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);

        $this->actingInWorkspace($editor, $workspace)
            ->delete(route('link-pages.destroy', $linkPage))
            ->assertRedirect(route('link-pages.index'));

        $this->assertModelMissing($linkPage);
        $this->assertModelExists($link);
        $this->get('/p/spring')->assertNotFound();
    }

    public function test_viewer_can_preview_but_not_change_link_pages(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'tickets', ['archived_at' => now()]);
        $linkPage = $this->linkPage($workspace, [], [$link]);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingInWorkspace($viewer, $workspace)
            ->get(route('link-pages.show', $linkPage))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('LinkPages/Show')
                ->where('linkPage.items.0.availability', 'archived')
                ->where('linkPage.items.0.short_url', 'https://go.example.test/tickets'));

        $this->actingInWorkspace($viewer, $workspace)->post(route('link-pages.store'), ['name' => 'X', 'path' => 'x'])->assertForbidden();
        $this->actingInWorkspace($viewer, $workspace)->patch(route('link-pages.update', $linkPage), [
            'name' => 'X', 'path' => 'spring', 'title' => 'X',
        ])->assertForbidden();
        $this->actingInWorkspace($viewer, $workspace)->post(route('link-pages.publish', $linkPage))->assertForbidden();
        $this->actingInWorkspace($viewer, $workspace)->delete(route('link-pages.destroy', $linkPage))->assertForbidden();

        $this->assertFalse($linkPage->fresh()->isPublished());
        $this->assertModelExists($linkPage);
    }

    public function test_link_pages_of_another_workspace_are_out_of_reach(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $foreign = $this->linkPage($otherWorkspace);

        $this->actingInWorkspace($owner, $workspace)->get(route('link-pages.show', $foreign))->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->post(route('link-pages.publish', $foreign))->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->delete(route('link-pages.destroy', $foreign))->assertForbidden();

        $this->assertFalse($foreign->fresh()->isPublished());
    }

    public function test_only_short_links_of_the_same_workspace_can_be_selected(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreignLink = $this->shortLink($otherWorkspace, $otherDomain, 'foreign');
        $linkPage = $this->linkPage($workspace);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), [
                'name' => 'Spring',
                'path' => 'spring',
                'title' => 'Spring',
                'items' => [['short_link_id' => $foreignLink->id]],
            ])
            ->assertSessionHasErrors('items.0.short_link_id');

        $this->assertSame(0, $linkPage->items()->count());
    }

    public function test_invalid_content_is_rejected(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'tickets');
        $linkPage = $this->linkPage($workspace);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), [
                'name' => '',
                'path' => 'Not valid!',
                'title' => str_repeat('a', 121),
                'description' => str_repeat('a', 281),
                'items' => [['short_link_id' => $link->id], ['short_link_id' => $link->id]],
            ])
            ->assertSessionHasErrors(['name', 'title', 'description', 'items.0.short_link_id', 'items.1.short_link_id']);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), ['name' => 'A', 'path' => 'Not valid!', 'title' => 'A'])
            ->assertSessionHasErrors('path');
    }

    public function test_short_link_options_show_whether_visitors_would_see_them(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'paused', ['is_enabled' => false]);
        $linkPage = $this->linkPage($workspace);

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('link-pages.show', $linkPage))
            ->assertInertia(fn ($page) => $page->where('shortLinks.0.availability', 'disabled'));

        $this->actingInWorkspace($owner, $workspace)
            ->getJson(route('link-pages.short-links', ['search' => 'paused']))
            ->assertOk()
            ->assertJsonPath('data.0.short_url', 'https://go.example.test/paused')
            ->assertJsonPath('data.0.availability', 'disabled');
    }
}
