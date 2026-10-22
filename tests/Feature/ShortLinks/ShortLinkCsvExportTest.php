<?php

namespace Tests\Feature\ShortLinks;

use App\Models\Folder;
use App\Models\Tag;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ShortLinkCsvExportTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_export_writes_the_versioned_columns_for_every_matching_link(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Spring, 2026']);
        $link = $this->shortLink($workspace, $domain, 'launch', [
            'folder_id' => $folder->id,
            'destination_url' => 'https://example.com/a?x=1,2',
            'fallback_url' => 'https://example.com/fallback',
            'is_enabled' => false,
            'activates_at' => '2026-10-21 08:30:00',
            'expires_at' => '2026-12-31 23:59:59',
            'visit_limit' => 50,
        ]);
        $link->tags()->sync([
            Tag::create(['workspace_id' => $workspace->id, 'name' => 'promo'])->id,
            Tag::create(['workspace_id' => $workspace->id, 'name' => 'café'])->id,
        ]);

        $rows = $this->exportRows($this->actingInWorkspace($owner, $workspace)->get(route('links.export')));

        $this->assertSame([
            'format_version', 'domain', 'slug', 'destination_url', 'fallback_url', 'folder', 'tags',
            'enabled', 'archived', 'activates_at', 'expires_at', 'visit_limit',
        ], $rows[0]);
        $this->assertSame([
            '1', 'go.example.test', 'launch', 'https://example.com/a?x=1,2', 'https://example.com/fallback',
            'Spring, 2026', 'café,promo', 'false', 'false', '2026-10-21T08:30:00Z', '2026-12-31T23:59:59Z', '50',
        ], $rows[1]);
    }

    public function test_export_starts_with_a_utf8_byte_order_mark_and_downloads_as_csv(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'one');

        $response = $this->actingInWorkspace($owner, $workspace)->get(route('links.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\u{FEFF}format_version,", $response->streamedContent());
        $this->assertStringContainsString('attachment; filename=openlink-links-v1-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_follows_the_list_filters(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $this->shortLink($workspace, $domain, 'filed', ['folder_id' => $folder->id]);
        $this->shortLink($workspace, $domain, 'loose');
        $this->shortLink($workspace, $domain, 'old', ['archived_at' => now()]);

        $client = $this->actingInWorkspace($owner, $workspace);

        $this->assertSame(['filed'], $this->slugs($client->get(route('links.export', ['folder' => $folder->id]))));
        $this->assertSame(['loose', 'filed'], $this->slugs($client->get(route('links.export'))));
        $this->assertSame(['old', 'loose', 'filed'], $this->slugs($client->get(route('links.export', ['status' => 'all']))));
        $this->assertSame(['old'], $this->slugs($client->get(route('links.export', ['status' => 'archived']))));
    }

    public function test_archived_links_export_their_archived_state(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'old', ['archived_at' => now()]);

        $rows = $this->exportRows($this->actingInWorkspace($owner, $workspace)->get(route('links.export', ['status' => 'archived'])));

        $this->assertSame('true', $rows[1][8]);
    }

    public function test_export_escapes_spreadsheet_formula_cells(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => '=HYPERLINK("https://evil.test")']);
        $link = $this->shortLink($workspace, $domain, 'formula', ['folder_id' => $folder->id]);
        $link->tags()->sync([Tag::create(['workspace_id' => $workspace->id, 'name' => '@SUM(A1)'])->id]);

        $rows = $this->exportRows($this->actingInWorkspace($owner, $workspace)->get(route('links.export')));

        $this->assertSame('\'=HYPERLINK("https://evil.test")', $rows[1][5]);
        $this->assertSame("'@SUM(A1)", $rows[1][6]);
    }

    public function test_export_never_contains_visitor_passwords_or_their_hashes(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'secret', ['password_hash' => Hash::make('hunter22')]);

        $csv = $this->actingInWorkspace($owner, $workspace)->get(route('links.export'))->streamedContent();

        $this->assertStringNotContainsString('hunter22', $csv);
        $this->assertStringNotContainsString('$2y$', $csv);
        $this->assertStringNotContainsString('password', $csv);
    }

    public function test_viewers_can_export_but_only_their_current_workspace(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain();
        $this->shortLink($workspace, $domain, 'mine');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $this->shortLink($other, $otherDomain, 'theirs');
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->assertSame(['mine'], $this->slugs($this->actingInWorkspace($viewer, $workspace)->get(route('links.export'))));
    }

    public function test_example_import_file_uses_the_export_columns(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();

        $response = $this->actingInWorkspace($owner, $workspace)->get(route('links.import.example'));

        $response->assertOk();
        $rows = $this->exportRows($response);
        $this->assertSame('format_version', $rows[0][0]);
        $this->assertGreaterThan(1, count($rows));
    }

    private function exportRows($response): array
    {
        $response->assertOk();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, ltrim($response->streamedContent(), "\u{FEFF}"));
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function slugs($response): array
    {
        return array_column(array_slice($this->exportRows($response), 1), 2);
    }
}
