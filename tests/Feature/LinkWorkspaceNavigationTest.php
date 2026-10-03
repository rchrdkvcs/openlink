<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkWorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_can_be_filtered_by_folder_or_unfiled(): void
    {
        [$workspace, $domain, $user] = $this->workspace();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $this->link($workspace, $domain, 'in-folder', ['folder_id' => $folder->id]);
        $this->link($workspace, $domain, 'loose');

        $this->actingAs($user)
            ->get(route('links.index', ['folder' => $folder->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('linksPagination.total', 1)
                ->where('links.0.slug', 'in-folder')
                ->where('filters.folder', (string) $folder->id));

        $this->get(route('links.index', ['folder' => 'unfiled']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('linksPagination.total', 1)
                ->where('links.0.slug', 'loose'));

        $this->get(route('links.index', ['folder' => 'nope']))->assertSessionHasErrors('folder');
    }

    public function test_shell_navigation_lists_folders_with_active_link_counts(): void
    {
        [$workspace, $domain, $user] = $this->workspace();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $this->link($workspace, $domain, 'one', ['folder_id' => $folder->id]);
        $this->link($workspace, $domain, 'two', ['folder_id' => $folder->id, 'archived_at' => now()]);
        $this->link($workspace, $domain, 'three');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('navigation.folders.0.name', 'Campaigns')
                ->where('navigation.folders.0.links_count', 1)
                ->where('navigation.links_count', 2)
                ->where('navigation.unfiled_count', 1)
                ->where('navigation.archived_count', 1)
                ->has('recentLinks', 2));
    }

    public function test_creating_a_link_flashes_the_created_link(): void
    {
        [, $domain, $user] = $this->workspace();

        $this->actingAs($user)
            ->from(route('links.index'))
            ->post(route('short-links.store'), [
                'domain_id' => $domain->id,
                'slug' => 'launch',
                'destination_url' => 'https://example.com/launch',
            ])
            ->assertRedirect(route('links.index'))
            ->assertSessionHas('inertia.flash_data.createdLink.short_url', 'https://go.example.test/launch');
    }

    public function test_workspace_settings_page_is_available_to_managers_only(): void
    {
        [$workspace, , $user] = $this->workspace();

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertRedirect('/settings/workspace');

        $this->get(route('settings.workspace'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Workspace')
                ->where('currentWorkspace.id', $workspace->id)
                ->where('canDelete', false));

        $viewer = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);

        $this->actingAs($viewer)
            ->withSession(['workspace_id' => $workspace->id])
            ->get(route('settings.workspace'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_legacy_settings_urls_redirect_to_their_new_location(): void
    {
        [, , $user] = $this->workspace();

        $this->actingAs($user)->get('/domains')->assertRedirect(route('domains.index'));
        $this->get('/members')->assertRedirect(route('members.index'));
        $this->get('/profile?tab=security')->assertRedirect(route('profile.edit', ['tab' => 'security']));
    }

    private function workspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Navigation',
            'slug' => 'navigation',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'go.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'navigation-token',
        ]);

        return [$workspace, $domain, $user];
    }

    private function link(Workspace $workspace, Domain $domain, string $slug, array $attributes = []): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
            ...$attributes,
        ]);
    }
}
