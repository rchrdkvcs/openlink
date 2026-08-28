<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Analytics\Outcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ViewerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_sees_folder_links_folders_and_analytics(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaigns']);
        $folderLink = $this->link($workspace, $domain, 'campaign', ['folder_id' => $folder->id]);
        $openLink = $this->link($workspace, $domain, 'open');
        $qrCode = QrCode::create([
            'short_link_id' => $folderLink->id,
            'name' => 'Poster',
            'token' => 'viewer-qr',
        ]);

        $this->event($workspace, $folderLink);
        $this->event($workspace, $openLink);

        $viewer = $this->viewer($workspace);

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
                ->where('link.slug', 'campaign'));

        $this->actingAsViewer($viewer, $workspace)
            ->get(route('qr-codes.export', [$qrCode, 'svg']))
            ->assertOk();
    }

    public function test_viewer_can_list_folder_links_and_analytics_via_api_but_cannot_mutate(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Private']);
        $folderLink = $this->link($workspace, $domain, 'secret', ['folder_id' => $folder->id]);
        $this->event($workspace, $folderLink);

        $viewer = $this->viewer($workspace);
        Sanctum::actingAs($viewer);

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
        [$workspace, $domain] = $this->workspaceAndDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $link = $this->link($workspace, $domain, 'kept', ['folder_id' => $folder->id]);
        $viewer = $this->viewer($workspace);

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

    private function viewer(Workspace $workspace): User
    {
        $viewer = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);

        return $viewer;
    }

    private function actingAsViewer(User $viewer, Workspace $workspace)
    {
        return $this->actingAs($viewer)
            ->withSession(['workspace_id' => $workspace->id])
            ->withHeader('Host', 'localhost');
    }

    /** @return array{0: Workspace, 1: Domain, 2: User} */
    private function workspaceAndDomain(string $hostname = 'localhost'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Viewer Co',
            'slug' => 'viewer-co',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $user];
    }

    private function link(Workspace $workspace, Domain $domain, string $slug, array $attributes = []): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/landing',
            ...$attributes,
        ]);
    }

    private function event(Workspace $workspace, ShortLink $link, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'workspace_id' => $workspace->id,
            'short_link_id' => $link->id,
            'domain_id' => $link->domain_id,
            'occurred_at' => now(),
            'metric' => 'visit',
            'outcome' => Outcome::SUCCESS,
            'is_bot' => false,
            'visitor_hash' => str()->random(32),
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'referrer_channel' => 'direct',
            ...$attributes,
        ]);
    }
}
