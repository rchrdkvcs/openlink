<?php

namespace Tests\Feature\ShortLinks;

use App\Actions\ShortLinks\LinksQuery;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinksQueryTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Query', 'slug' => 'query', 'settings' => []]);
        $this->domain = Domain::create([
            'workspace_id' => $this->workspace->id,
            'hostname' => 'go.query.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'query-token',
        ]);
    }

    public function test_default_status_hides_archived_links_and_all_includes_them(): void
    {
        $this->link('live');
        $this->link('gone', ['archived_at' => now()]);

        $this->assertSame(['live'], $this->slugs([]));
        $this->assertEqualsCanonicalizing(['live', 'gone'], $this->slugs(['status' => LinksQuery::ALL_STATUSES]));
        $this->assertSame(['gone'], $this->slugs(['status' => 'archived']));
        $this->assertSame([], $this->slugs(['status' => 'unknown']));
    }

    public function test_search_matches_slug_destination_and_full_short_url(): void
    {
        $this->link('spring', ['destination_url' => 'https://shop.example/sale']);
        $this->link('winter');

        $this->assertSame(['spring'], $this->slugs(['search' => 'SPRING']));
        $this->assertSame(['spring'], $this->slugs(['search' => 'shop.example']));
        $this->assertSame(['winter'], $this->slugs(['search' => 'go.query.test/wint']));
    }

    public function test_folder_and_tag_filters_narrow_results(): void
    {
        $folder = Folder::create(['workspace_id' => $this->workspace->id, 'name' => 'Campaigns']);
        $tagged = $this->link('tagged', ['folder_id' => $folder->id]);
        $this->link('loose');
        $tag = Tag::create(['workspace_id' => $this->workspace->id, 'name' => 'promo']);
        $tagged->tags()->attach($tag);

        $this->assertSame(['tagged'], $this->slugs(['folder' => (string) $folder->id]));
        $this->assertSame(['loose'], $this->slugs(['folder' => 'unfiled']));
        $this->assertSame(['tagged'], $this->slugs(['tag' => 'promo']));
    }

    public function test_counts_and_recent_links(): void
    {
        $this->link('one');
        $this->link('two', ['is_enabled' => false]);
        $this->link('three', ['archived_at' => now()]);

        $query = app(LinksQuery::class);

        $this->assertSame(['total' => 3, 'active' => 1], $query->counts($this->workspace));
        $this->assertCount(1, $query->recent($this->workspace, 1));
        $this->assertSame(['currentPage' => 1, 'lastPage' => 1, 'total' => 2, 'perPage' => 50], LinksQuery::pagination($query->paginate($this->workspace)));
    }

    private function slugs(array $filters): array
    {
        return collect(app(LinksQuery::class)->paginate($this->workspace, $filters)->items())->pluck('slug')->all();
    }

    private function link(string $slug, array $attributes = []): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $this->workspace->id,
            'domain_id' => $this->domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
            ...$attributes,
        ]);
    }
}
