<?php

namespace Tests\Feature\SmartRouting;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RoutingDraftConcurrencyTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_routing_save_keeps_an_authorized_inspected_link_after_it_leaves_search_results(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = app(ShortLinkMutation::class)->create($owner, $workspace, [
            'domain_id' => $domain->id, 'slug' => 'launch',
            'destination_url' => 'https://example.com/only-search-term', 'is_enabled' => true,
        ]);
        $url = route('links.index', ['search' => 'only-search-term', 'link' => $link->id]);
        $this->actingInWorkspace($owner, $workspace)->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('links', 1)->where('inspectedLink.id', $link->id));
        $this->from($url)->put(route('short-links.routing.update', $link), [
            'destination_url' => 'https://example.com/new', 'routing_rules' => [], 'expected_revision' => 1,
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('links', 0)->where('inspectedLink.id', $link->id)
            ->where('inspectedLink.destination_url', 'https://example.com/new')->where('inspectedLink.revision', 2));
    }

    public function test_a_failed_routing_validation_does_not_allow_a_stale_drawer_to_overwrite_another_member(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $mutations = app(ShortLinkMutation::class);
        $link = $mutations->create($owner, $workspace, [
            'domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/original', 'is_enabled' => true,
        ]);
        $editor = $this->memberOf($workspace, WorkspaceRole::Editor->value);
        $drawerRevision = $link->revision;
        $mutations->update($editor, $link, [
            'slug' => 'saved-by-b', 'destination_url' => $link->destination_url, 'is_enabled' => true, 'expected_revision' => $drawerRevision,
        ]);
        $url = route('links.index', ['link' => $link->id]);
        $this->actingInWorkspace($owner, $workspace)->from($url)
            ->put(route('short-links.routing.update', $link), [
                'destination_url' => 'invalid', 'routing_rules' => [], 'expected_revision' => $drawerRevision,
            ])->assertSessionHasErrors('destination_url')->assertSessionMissing('inertia.flash_data.routingSaved');
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('inspectedLink.revision', 2)->where('inspectedLink.slug', 'saved-by-b'));
        $this->patch(route('short-links.update', $link), [
            'slug' => 'draft-by-a', 'destination_url' => 'https://example.com/original', 'is_enabled' => true, 'expected_revision' => $drawerRevision,
        ])->assertSessionHasErrors('expected_revision');
        $this->assertSame('saved-by-b', $link->fresh()->slug);
        $this->assertSame(2, $link->fresh()->revision);
    }

    public function test_routing_rejects_a_valid_draft_from_a_stale_revision_without_writing_or_issuing_a_receipt(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $mutations = app(ShortLinkMutation::class);
        $link = $mutations->create($owner, $workspace, [
            'domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/original', 'is_enabled' => true,
        ]);
        $mutations->update($owner, $link, [
            'destination_url' => 'https://example.com/saved-by-b', 'is_enabled' => true, 'expected_revision' => 1,
        ]);
        $this->actingInWorkspace($owner, $workspace)->put(route('short-links.routing.update', $link), [
            'destination_url' => 'https://example.com/stale', 'routing_rules' => [], 'expected_revision' => 1,
        ])->assertSessionHasErrors('expected_revision')->assertSessionMissing('inertia.flash_data.routingSaved');
        $this->assertSame('https://example.com/saved-by-b', $link->fresh()->destination_url);
        $this->assertSame(2, $link->fresh()->revision);
    }

    public function test_the_receipt_identifies_our_mutation_even_if_another_member_edits_before_the_redirected_page_loads(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $mutations = app(ShortLinkMutation::class);
        $link = $mutations->create($owner, $workspace, [
            'domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/original', 'is_enabled' => true,
        ]);
        $url = route('links.index', ['link' => $link->id]);
        $this->actingInWorkspace($owner, $workspace)->from($url)->put(route('short-links.routing.update', $link), [
            'destination_url' => 'https://example.com/routing', 'routing_rules' => [], 'expected_revision' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('inertia.flash_data.routingSaved', [
            'link_id' => $link->id, 'expected_revision' => 1, 'revision' => 2,
        ]);
        $mutations->update($owner, $link->fresh(), [
            'slug' => 'saved-by-b', 'destination_url' => 'https://example.com/routing', 'is_enabled' => true, 'expected_revision' => 2,
        ]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('inspectedLink.revision', 3)->where('inspectedLink.slug', 'saved-by-b')
            ->hasFlash('routingSaved.revision', 2)->hasFlash('routingSaved.expected_revision', 1));
        $this->patch(route('short-links.update', $link), [
            'slug' => 'draft-by-a', 'destination_url' => 'https://example.com/original', 'is_enabled' => true, 'expected_revision' => 1,
        ])->assertSessionHasErrors('expected_revision');
        $this->assertSame('saved-by-b', $link->fresh()->slug);
        $this->assertSame('https://example.com/routing', $link->fresh()->destination_url);
    }

    public function test_an_authorized_routing_save_can_be_followed_by_a_drawer_save_on_its_receipted_revision(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = app(ShortLinkMutation::class)->create($owner, $workspace, [
            'domain_id' => $domain->id, 'slug' => 'launch', 'destination_url' => 'https://example.com/original', 'is_enabled' => true,
        ]);
        $this->actingInWorkspace($owner, $workspace)->put(route('short-links.routing.update', $link), [
            'destination_url' => 'https://example.com/routing', 'routing_rules' => [], 'expected_revision' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('inertia.flash_data.routingSaved.revision', 2);
        $this->patch(route('short-links.update', $link), [
            'slug' => 'draft-by-a', 'destination_url' => 'https://example.com/routing', 'is_enabled' => true, 'expected_revision' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame('draft-by-a', $link->fresh()->slug);
        $this->assertSame(3, $link->fresh()->revision);
    }

    public function test_dashboard_routing_requires_its_starting_revision(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)->put(route('short-links.routing.update', $link), [
            'destination_url' => 'https://example.com/routing', 'routing_rules' => [],
        ])->assertSessionHasErrors('expected_revision');
        $this->assertSame(0, $link->fresh()->revision);
    }

    public function test_inspection_never_retains_a_foreign_deleted_or_unarchived_link_in_the_archive(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->shortLink($other, $otherDomain, 'foreign');
        $this->actingInWorkspace($owner, $workspace)->get(route('links.index', ['link' => $foreign->id]))
            ->assertInertia(fn (Assert $page) => $page->where('inspectedLink', null));
        $link = $this->shortLink($workspace, $domain, 'archived', ['archived_at' => now()]);
        $archive = route('links.index', ['status' => 'archived', 'link' => $link->id]);
        $this->get($archive)->assertInertia(fn (Assert $page) => $page->where('inspectedLink.id', $link->id));
        $this->from($archive)->post(route('short-links.unarchive', $link), ['expected_revision' => 0])->assertSessionHasNoErrors();
        $this->get($archive)->assertInertia(fn (Assert $page) => $page->where('inspectedLink', null)->has('links', 0));
        $this->delete(route('short-links.destroy', $link))->assertRedirect();
        $this->get(route('links.index', ['link' => $link->id]))->assertInertia(fn (Assert $page) => $page->where('inspectedLink', null));
    }

    public function test_partial_reloads_revalidate_the_inspected_link_even_when_only_polling_health(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'launch');
        $this->actingInWorkspace($owner, $workspace)
            ->get(route('links.index', ['link' => $link->id, 'search' => 'nothing-matches']))
            ->assertInertia(fn (Assert $page) => $page->where('inspectedLink.id', $link->id)
                ->reloadOnly('destinationHealth', fn (Assert $partial) => $partial
                    ->where('inspectedLink.id', $link->id)->missing('links')));
    }
}
