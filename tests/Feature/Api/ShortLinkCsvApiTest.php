<?php

namespace Tests\Feature\Api;

use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ShortLinkCsvApiTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_api_export_includes_archived_links_by_default(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'live');
        $this->shortLink($workspace, $domain, 'old', ['archived_at' => now()]);
        Sanctum::actingAs($owner);

        $csv = $this->withHeader('X-Workspace-Id', (string) $workspace->id)->get('/api/v1/links/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('go.example.test,live,', $csv);
        $this->assertStringContainsString('go.example.test,old,', $csv);
    }

    public function test_api_dry_run_previews_and_import_creates(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        Sanctum::actingAs($owner);
        $file = fn () => UploadedFile::fake()->createWithContent('links.csv', "slug,url\nhello,https://example.com\n");
        $client = $this->withHeader('X-Workspace-Id', (string) $workspace->id);

        $client->postJson('/api/v1/links/import', ['file' => $file(), 'dry_run' => true, 'domain_id' => $domain->id])
            ->assertOk()->assertJsonPath('summary.ready', 1);
        $this->assertDatabaseCount('short_links', 0);

        $client->postJson('/api/v1/links/import', ['file' => $file(), 'domain_id' => $domain->id])
            ->assertOk()->assertJsonPath('summary.created', 1)->assertJsonPath('rows.0.short_url', 'https://go.example.test/hello');
    }

    public function test_api_import_validation_errors_are_json_and_viewers_are_forbidden(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($owner);

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/v1/links/import', ['file' => UploadedFile::fake()->createWithContent('links.csv', "name\nx\n")])
            ->assertUnprocessable()->assertJsonValidationErrors('mapping');

        Sanctum::actingAs($this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER));

        $this->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/v1/links/import', ['file' => UploadedFile::fake()->createWithContent('links.csv', "url\nhttps://example.com\n")])
            ->assertForbidden();
    }
}
