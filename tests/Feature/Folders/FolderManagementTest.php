<?php

namespace Tests\Feature\Folders;

use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class FolderManagementTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_editor_can_see_and_edit_links_in_folders_without_managing_the_folders(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $link = $this->shortLink($workspace, $domain, 'campaign', [
            'folder_id' => $folder->id,
            'destination_url' => 'https://example.com/campaign',
        ]);

        $this->actingInWorkspace($editor, $workspace)
            ->get(route('links.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('folders', 1)
                ->where('folders.0.id', $folder->id)
                ->has('links', 1)
                ->where('links.0.id', $link->id));

        $this->actingInWorkspace($editor, $workspace)
            ->patch(route('short-links.update', $link), [
                'destination_url' => 'https://example.com/updated',
                'domain_id' => $domain->id,
                'slug' => $link->slug,
                'folder_id' => $folder->id,
                'is_enabled' => true,
            ])
            ->assertRedirect();

        $this->actingInWorkspace($editor, $workspace)
            ->patch(route('folders.update', $folder), ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_link_forms_accept_select_values_as_the_ui_sends_them(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.store'), [
                'domain_id' => $domain->id,
                'destination_url' => 'https://example.com/filed',
                'slug' => 'filed',
                'folder_id' => (string) $folder->id,
            ])
            ->assertSessionHasNoErrors();

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.store'), [
                'domain_id' => $domain->id,
                'destination_url' => 'https://example.com/unfiled',
                'slug' => 'unfiled',
                'folder_id' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($folder->id, ShortLink::query()->where('slug', 'filed')->value('folder_id'));
        $this->assertNull(ShortLink::query()->where('slug', 'unfiled')->value('folder_id'));
    }

    public function test_workspace_manager_can_rename_and_delete_folder_leaving_links_unfiled(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $link = $this->shortLink($workspace, $domain, 'filed', [
            'folder_id' => $folder->id,
            'destination_url' => 'https://example.com/filed',
        ]);

        $this->actingInWorkspace($user, $workspace)
            ->patch(route('folders.update', $folder), ['name' => 'Renamed Campaign'])
            ->assertRedirect();

        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Renamed Campaign']);

        $this->actingInWorkspace($user, $workspace)
            ->delete(route('folders.destroy', $folder))
            ->assertRedirect();

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'folder_id' => null]);
    }

    public function test_viewer_cannot_rename_or_delete_folder(): void
    {
        [$workspace] = $this->workspaceWithActiveDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);

        $this->actingInWorkspace($viewer, $workspace)
            ->patch(route('folders.update', $folder), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingInWorkspace($viewer, $workspace)
            ->delete(route('folders.destroy', $folder))
            ->assertForbidden();

        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Campaign']);
    }

    public function test_short_link_can_be_moved_between_folders_but_not_across_workspaces(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $link = $this->shortLink($workspace, $domain, 'movable', ['destination_url' => 'https://example.com/movable']);

        $this->actingInWorkspace($user, $workspace)
            ->post(route('short-links.move', $link), ['folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'folder_id' => $folder->id]);

        $otherWorkspace = $this->workspaceFor(User::factory()->create(), 'Other', 'other');
        $foreignFolder = Folder::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Foreign']);

        $this->actingInWorkspace($user, $workspace)
            ->post(route('short-links.move', $link), ['folder_id' => $foreignFolder->id])
            ->assertSessionHasErrors('folder_id');

        $this->actingInWorkspace($user, $workspace)
            ->post(route('short-links.move', $link), ['folder_id' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'folder_id' => null]);
    }
}
