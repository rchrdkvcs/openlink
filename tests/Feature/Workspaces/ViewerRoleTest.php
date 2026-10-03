<?php

namespace Tests\Feature\Workspaces;

use App\Models\Folder;
use App\Models\QrCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ViewerRoleTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_viewer_sees_folder_links_folders_and_analytics(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $folderLink = $this->shortLink($workspace, $domain, 'campaign', ['folder_id' => $folder->id]);
        $openLink = $this->shortLink($workspace, $domain, 'open');
        $qrCode = QrCode::create([
            'short_link_id' => $folderLink->id,
            'name' => 'Poster',
            'token' => 'viewer-qr',
        ]);

        $this->analyticsEvent($folderLink);
        $this->analyticsEvent($openLink);

        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $links = $this->actingAsViewer($viewer, $workspace)
            ->get(route('links.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Links/Index')
                ->where('canEditWorkspace', false)
                ->has('folders', 1)
                ->has('links', 2))
            ->viewData('page')['props']['links'];

        $this->assertEqualsCanonicalizing(
            ['campaign', 'open'],
            collect($links)->pluck('slug')->all(),
        );

        $this->actingAsViewer($viewer, $workspace)
            ->get(route('analytics.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('report.summary.visits', 2)
                ->has('filterOptions.links', 2));

        $this->actingAsViewer($viewer, $workspace)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('analytics.summary.visits', 2)
                ->has('analytics.top_links', 2));

        $this->actingAsViewer($viewer, $workspace)
            ->get(route('qr-codes.show', $qrCode))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QrCodes/Show')
                ->where('qr.token', 'viewer-qr')
                ->where('qr.short_link.slug', 'campaign'));

        $this->actingAsViewer($viewer, $workspace)
            ->get(route('qr-codes.export', [$qrCode, 'svg']))
            ->assertOk();
    }

    public function test_viewer_can_list_folder_links_and_analytics_via_api_but_cannot_mutate(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Private']);
        $folderLink = $this->shortLink($workspace, $domain, 'secret', ['folder_id' => $folder->id]);
        $this->analyticsEvent($folderLink);

        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER));

        $this->getJson('/api/v1/links')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'secret');

        $this->getJson('/api/v1/folders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Private');

        $this->getJson('/api/v1/analytics')
            ->assertOk()
            ->assertJsonPath('data.summary.visits', 1);

        $this->postJson('/api/v1/links', [
            'domain_id' => $domain->id,
            'destination_url' => 'https://example.com/forbidden',
        ])->assertForbidden();

        $this->patchJson('/api/v1/links/'.$folderLink->id, [
            'destination_url' => 'https://example.com/hijacked',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/links/'.$folderLink->id)->assertForbidden();
        $this->postJson('/api/v1/folders', ['name' => 'Hijacked'])->assertForbidden();
    }

    public function test_viewer_cannot_create_or_change_links_or_folders(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $link = $this->shortLink($workspace, $domain, 'kept', ['folder_id' => $folder->id]);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAsViewer($viewer, $workspace)
            ->post(route('short-links.store'), [
                'domain_id' => $domain->id,
                'destination_url' => 'https://example.com/new',
            ])
            ->assertForbidden();

        $this->actingAsViewer($viewer, $workspace)
            ->patch(route('short-links.update', $link), [
                'destination_url' => 'https://example.com/hijacked',
            ])
            ->assertForbidden();

        $this->actingAsViewer($viewer, $workspace)
            ->post(route('folders.store'), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAsViewer($viewer, $workspace)
            ->patch(route('folders.update', $folder), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAsViewer($viewer, $workspace)
            ->delete(route('folders.destroy', $folder))
            ->assertForbidden();

        $this->assertDatabaseHas('short_links', [
            'id' => $link->id,
            'destination_url' => 'https://example.com/landing',
        ]);
        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Campaign']);
        $this->assertDatabaseCount('short_links', 1);
    }

    private function actingAsViewer(User $viewer, Workspace $workspace): static
    {
        return $this->actingInWorkspace($viewer, $workspace)->withHeader('Host', 'localhost');
    }
}
