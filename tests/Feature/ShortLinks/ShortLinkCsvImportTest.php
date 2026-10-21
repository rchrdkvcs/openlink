<?php

namespace Tests\Feature\ShortLinks;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesAnalyticsEvents;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ShortLinkCsvImportTest extends TestCase
{
    use CreatesAnalyticsEvents;
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const HEADER = 'format_version,domain,slug,destination_url,fallback_url,folder,tags,enabled,archived,activates_at,expires_at,visit_limit';

    public function test_preview_reports_row_outcomes_without_creating_links_folders_or_tags(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('go.example.test');

        $response = $this->flashed('linkImportPreview', $this->actingInWorkspace($owner, $workspace), route('short-links.import.preview'), [
            'file' => $this->csv([
                self::HEADER,
                '1,go.example.test,launch,https://example.com/launch,,Campaigns,"spring,promo",true,false,,,',
                '1,go.example.test,,not-a-url,,,,,,,,',
            ]),
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.ready', 1)
            ->assertJsonPath('summary.invalid', 1)
            ->assertJsonPath('summary.new_folders', ['Campaigns'])
            ->assertJsonPath('summary.new_tags', ['spring', 'promo'])
            ->assertJsonPath('rows.0.row', 2)
            ->assertJsonPath('rows.0.status', 'ready')
            ->assertJsonPath('rows.0.short_url', 'https://go.example.test/launch')
            ->assertJsonPath('rows.1.row', 3)
            ->assertJsonPath('rows.1.status', 'invalid')
            ->assertJsonPath('mapping.destination_url', 3);

        $this->assertDatabaseCount('short_links', 0);
        $this->assertDatabaseCount('folders', 0);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_import_creates_valid_rows_and_skips_invalid_ones_with_reasons(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithPreferredDomain('go.example.test');

        $response = $this->flashed('linkImportResult', $this->actingInWorkspace($owner, $workspace), route('short-links.import'), [
            'file' => $this->csv([
                self::HEADER,
                '1,go.example.test,launch,https://example.com/launch,https://example.com/fallback,Campaigns,"spring,promo",false,true,2026-11-01T10:00:00+02:00,2026-12-01,25',
                '1,go.example.test,bad,javascript:alert(1),,,,,,,,',
            ]),
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.skipped', 1)
            ->assertJsonPath('rows.0.status', 'created')
            ->assertJsonPath('rows.1.status', 'skipped');
        $this->assertNotEmpty($response->json('rows.1.errors'));

        $link = ShortLink::query()->where('slug', 'launch')->with(['folder', 'tags'])->sole();
        $this->assertSame($workspace->id, $link->workspace_id);
        $this->assertSame($domain->id, $link->domain_id);
        $this->assertSame('https://example.com/fallback', $link->fallback_url);
        $this->assertSame('Campaigns', $link->folder->name);
        $this->assertSame($workspace->id, $link->folder->workspace_id);
        $this->assertEqualsCanonicalizing(['spring', 'promo'], $link->tags->pluck('name')->all());
        $this->assertFalse($link->is_enabled);
        $this->assertTrue($link->isArchived());
        $this->assertSame('2026-11-01 08:00:00', $link->activates_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 00:00:00', $link->expires_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(25, $link->visit_limit);
        $this->assertSame(0, (int) $link->successful_visits);
    }

    public function test_exported_files_round_trip_into_another_workspace(): void
    {
        [$source, $domain, $owner] = $this->workspaceWithPreferredDomain('go.example.test');
        $folder = Folder::create(['workspace_id' => $source->id, 'name' => "=Line one\nline two"]);
        $link = $this->shortLink($source, $domain, 'cafe-launch', [
            'folder_id' => $folder->id,
            'destination_url' => 'https://example.com/ünïcode?q=a,b;c',
            'activates_at' => '2026-10-21 08:30:00',
            'visit_limit' => 10,
            'is_enabled' => false,
        ]);
        $link->tags()->sync([Tag::create(['workspace_id' => $source->id, 'name' => '+plus'])->id, Tag::create(['workspace_id' => $source->id, 'name' => 'b'])->id]);
        $csv = $this->actingInWorkspace($owner, $source)->get(route('links.export'))->streamedContent();

        $target = $this->workspaceFor($owner, 'Target');
        $targetDomain = Domain::create([
            'workspace_id' => $target->id,
            'hostname' => 'new.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => Str::random(16),
        ]);

        $this->flashed('linkImportResult', $this->actingInWorkspace($owner, $target), route('short-links.import'), [
            'file' => UploadedFile::fake()->createWithContent('links.csv', $csv),
            'mapping' => ['slug' => 2, 'destination_url' => 3, 'folder' => 5, 'tags' => 6, 'enabled' => 7, 'activates_at' => 9, 'visit_limit' => 11],
            'domain_id' => $targetDomain->id,
        ])->assertOk()->assertJsonPath('rows.0.errors', [])->assertJsonPath('summary.created', 1);

        $copy = $target->shortLinks()->with(['folder', 'tags'])->sole();
        $this->assertSame($targetDomain->id, $copy->domain_id);
        $this->assertSame('cafe-launch', $copy->slug);
        $this->assertSame('https://example.com/ünïcode?q=a,b;c', $copy->destination_url);
        $this->assertSame("=Line one\nline two", $copy->folder->name);
        $this->assertEqualsCanonicalizing(['+plus', 'b'], $copy->tags->pluck('name')->all());
        $this->assertFalse($copy->is_enabled);
        $this->assertSame('2026-10-21 08:30:00', $copy->activates_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(10, $copy->visit_limit);
    }

    public function test_semicolon_separated_files_without_byte_order_mark_are_read(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('go.example.test');

        $this->flashed('linkImportPreview', $this->actingInWorkspace($owner, $workspace), route('short-links.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('links.csv', "slug;url\r\nhello;\"https://example.com/a;b\"\r\n"),
        ])->assertOk()
            ->assertJsonPath('mapping', ['slug' => 0, 'destination_url' => 1])
            ->assertJsonPath('rows.0.status', 'ready')
            ->assertJsonPath('rows.0.destination_url', 'https://example.com/a;b');
    }

    public function test_slug_conflicts_and_in_file_duplicates_are_never_overwritten(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithPreferredDomain('go.example.test');
        $existing = $this->shortLink($workspace, $domain, 'taken', ['destination_url' => 'https://example.com/original', 'successful_visits' => 7]);
        $this->analyticsEvent($existing);

        $response = $this->flashed('linkImportResult', $this->actingInWorkspace($owner, $workspace), route('short-links.import'), [
            'file' => $this->csv([
                'slug,destination_url,successful_visits',
                'taken,https://example.com/replacement,0',
                'fresh,https://example.com/one,999',
                'fresh,https://example.com/two,0',
            ]),
        ]);

        $response->assertOk()
            ->assertJsonPath('rows.0.status', 'skipped')
            ->assertJsonPath('rows.0.errors.0', 'This short link path is already used on this domain.')
            ->assertJsonPath('rows.1.status', 'created')
            ->assertJsonPath('rows.2.status', 'skipped')
            ->assertJsonPath('rows.2.errors.0', 'Duplicate of row 3.');

        $existing->refresh();
        $this->assertSame('https://example.com/original', $existing->destination_url);
        $this->assertSame(7, (int) $existing->successful_visits);
        $this->assertSame(1, $existing->analyticsEvents()->count());
        $this->assertSame(0, (int) ShortLink::query()->where('slug', 'fresh')->value('successful_visits'));
    }

    public function test_retrying_an_import_never_duplicates_generated_links(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('go.example.test');
        $file = fn () => $this->csv([
            'destination_url,folder',
            'https://example.com/generated,Docs',
            'https://example.com/generated,Docs',
            'https://example.com/other,',
        ]);
        $client = $this->actingInWorkspace($owner, $workspace);

        $this->flashed('linkImportResult', $client, route('short-links.import'), ['file' => $file()])
            ->assertOk()
            ->assertJsonPath('summary.created', 2)
            ->assertJsonPath('rows.1.errors.0', 'Duplicate of row 2.');

        $this->flashed('linkImportResult', $client, route('short-links.import'), ['file' => $file()])
            ->assertOk()
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('rows.0.errors.0', 'This link was already imported.')
            ->assertJsonPath('rows.2.errors.0', 'This link was already imported.');

        $this->assertSame(2, $workspace->shortLinks()->count());
    }

    public function test_reserved_slugs_loops_and_invalid_values_are_rejected_per_row(): void
    {
        app(InstanceSettings::class)->set('reserved_slugs', ['dashboard']);
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('localhost');

        $response = $this->flashed('linkImportPreview', $this->actingInWorkspace($owner, $workspace), route('short-links.import.preview'), [
            'file' => $this->csv([
                self::HEADER,
                '1,localhost,dashboard,https://example.com,,,,,,,,',
                '1,localhost,loop,https://localhost/loop,,,,,,,,',
                '2,localhost,future,https://example.com,,,,,,,,',
                '1,localhost,flags,https://example.com,,,,maybe,sometimes,,,',
                '1,localhost,dates,https://example.com,,,,,,21/10/2026,2026-02-31,',
                '1,localhost,window,https://example.com,,,,,,2026-12-01,2026-11-01,',
                '1,localhost,limit,https://example.com,,,,,,,,-5',
                '1,localhost,"bad slug",https://example.com,,,,,,,,',
            ]),
        ]);

        $response->assertOk()->assertJsonPath('summary.ready', 0);
        $errors = collect($response->json('rows'))->pluck('errors');
        $this->assertSame(['This short link path is reserved by the application.'], $errors[0]);
        $this->assertSame(['Destination URL cannot point to the same short URL.'], $errors[1]);
        $this->assertSame(['Format version 2 is not supported. Use version 1.'], $errors[2]);
        $this->assertSame(['Enabled must be true or false.', 'Archived must be true or false.'], $errors[3]);
        $this->assertCount(2, $errors[4]);
        $this->assertSame(['Expiration must be after activation.'], $errors[5]);
        $this->assertSame(['The visit limit must be at least 1.'], $errors[6]);
        $this->assertSame(['Use letters, numbers, dashes, underscores, and slashes only.'], $errors[7]);
    }

    public function test_domains_owned_by_other_workspaces_or_unknown_are_rejected(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('go.example.test');
        $this->workspaceWithPreferredDomain('foreign.example.test');

        $response = $this->flashed('linkImportPreview', $this->actingInWorkspace($owner, $workspace), route('short-links.import.preview'), [
            'file' => $this->csv([
                'domain,slug,destination_url,workspace_id',
                'foreign.example.test,promo,https://example.com,999',
                'bit.ly,abc,https://example.com,999',
                'GO.EXAMPLE.TEST,promo,https://example.com,999',
            ]),
        ]);

        $response->assertOk()
            ->assertJsonPath('rows.0.status', 'invalid')
            ->assertJsonPath('rows.0.errors.0', 'The domain foreign.example.test is not connected to this workspace. Leave the domain empty or choose a domain for the import to use one you control.')
            ->assertJsonPath('rows.1.status', 'invalid')
            ->assertJsonPath('rows.2.status', 'ready');
    }

    public function test_inactive_domains_and_foreign_domain_ids_are_rejected(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('go.example.test');
        Domain::create(['workspace_id' => $workspace->id, 'hostname' => 'pending.example.test', 'status' => Domain::STATUS_PENDING, 'verification_token' => Str::random(16)]);
        [, $foreignDomain] = $this->workspaceWithPreferredDomain('foreign.example.test');

        $client = $this->actingInWorkspace($owner, $workspace);

        $this->flashed('linkImportPreview', $client, route('short-links.import.preview'), [
            'file' => $this->csv(['domain,destination_url', 'pending.example.test,https://example.com']),
        ])->assertOk()->assertJsonPath('rows.0.errors.0', 'The domain pending.example.test is not active yet.');

        $this->flashed('linkImportResult', $client, route('short-links.import'), [
            'file' => $this->csv(['destination_url', 'https://example.com']),
            'domain_id' => $foreignDomain->id,
        ])->assertOk()->assertJsonPath('summary.created', 0)->assertJsonPath('rows.0.errors.0', 'Domain does not belong to this workspace.');

        $this->assertDatabaseCount('short_links', 0);
    }

    public function test_formula_cells_are_stored_as_text_and_escaped_cells_are_unescaped(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain('go.example.test');

        $this->flashed('linkImportResult', $this->actingInWorkspace($owner, $workspace), route('short-links.import'), [
            'file' => $this->csv([
                'slug,destination_url,folder,tags',
                'one,https://example.com,"=HYPERLINK(""https://evil.test"")",\'@cmd',
                'two,=HYPERLINK("https://evil.test"),,',
            ]),
        ])->assertOk()->assertJsonPath('summary.created', 1)->assertJsonPath('rows.1.status', 'skipped');

        $link = ShortLink::query()->where('slug', 'one')->with(['folder', 'tags'])->sole();
        $this->assertSame('=HYPERLINK("https://evil.test")', $link->folder->name);
        $this->assertSame(['@cmd'], $link->tags->pluck('name')->all());
    }

    public function test_column_mapping_must_include_the_destination_and_match_the_file(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain();
        $client = $this->actingInWorkspace($owner, $workspace);

        $client->post(route('short-links.import.preview'), ['file' => $this->csv(['name,notes', 'a,b'])])
            ->assertSessionHasErrors('mapping');

        $client->post(route('short-links.import.preview'), [
            'file' => $this->csv(['url', 'https://example.com']),
            'mapping' => ['destination_url' => 4],
        ])->assertSessionHasErrors('mapping');
    }

    public function test_oversized_non_utf8_empty_and_overlong_files_are_rejected(): void
    {
        [$workspace, , $owner] = $this->workspaceWithPreferredDomain();
        $client = $this->actingInWorkspace($owner, $workspace);

        $client->post(route('short-links.import.preview'), ['file' => UploadedFile::fake()->create('links.csv', 2048, 'text/csv')])
            ->assertSessionHasErrors('file');
        $client->post(route('short-links.import.preview'), ['file' => UploadedFile::fake()->createWithContent('links.csv', "url\nhttps://example.com/\xC3\x28\n")])
            ->assertSessionHasErrors(['file' => 'The file must be encoded as UTF-8.']);
        $client->post(route('short-links.import.preview'), ['file' => UploadedFile::fake()->createWithContent('links.csv', "url\n")])
            ->assertSessionHasErrors('file');
        $client->post(route('short-links.import.preview'), ['file' => UploadedFile::fake()->createWithContent('links.exe', "url\nhttps://example.com\n")])
            ->assertSessionHasErrors('file');

        $rows = array_map(fn (int $i) => 'https://example.com/'.$i, range(1, 1001));
        $client->post(route('short-links.import.preview'), ['file' => $this->csv(['url', ...$rows])])
            ->assertSessionHasErrors(['file' => 'The file has more than 1000 links. Split it into smaller files.']);
    }

    public function test_viewers_cannot_preview_or_import(): void
    {
        [$workspace] = $this->workspaceWithPreferredDomain();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $client = $this->actingInWorkspace($viewer, $workspace);

        $client->post(route('short-links.import.preview'), ['file' => $this->csv(['url', 'https://example.com'])])->assertForbidden();
        $client->post(route('short-links.import'), ['file' => $this->csv(['url', 'https://example.com'])])->assertForbidden();

        $this->assertDatabaseCount('short_links', 0);
    }

    public function test_editors_can_import(): void
    {
        [$workspace] = $this->workspaceWithPreferredDomain();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);

        $this->flashed('linkImportResult', $this->actingInWorkspace($editor, $workspace), route('short-links.import'), ['file' => $this->csv(['url', 'https://example.com'])])
            ->assertOk()->assertJsonPath('summary.created', 1);
    }

    private function flashed(string $key, self $client, string $url, array $data): TestResponse
    {
        $client->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();

        return TestResponse::fromBaseResponse(response()->json(session('inertia.flash_data.'.$key)));
    }

    private function workspaceWithPreferredDomain(string $hostname = 'localhost'): array
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain($hostname);
        $workspace->update(['preferred_domain_id' => $domain->id]);

        return [$workspace, $domain, $owner];
    }

    private function csv(array $lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('links.csv', implode("\n", $lines)."\n");
    }
}
