<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Services\InstanceSettings;
use App\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesLinkPages;
use Tests\TestCase;

class LinkPageAddressTest extends TestCase
{
    use CreatesLinkPages;
    use RefreshDatabase;

    public function test_page_prefix_is_reserved_for_application_host_short_links_even_when_settings_change(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('localhost');
        app(InstanceSettings::class)->set('reserved_prefixes', []);
        app(InstanceSettings::class)->set('reserved_slugs', []);

        foreach (['p', 'p/spring', 'P/Spring'] as $slug) {
            $this->assertTrue(app(SlugService::class)->isReservedForDomain($domain, $slug), $slug);
        }

        $this->assertFalse(app(SlugService::class)->isReservedForDomain($domain, 'pages'));
    }

    public function test_page_prefix_is_free_on_workspace_domains(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.example.test');

        $this->assertFalse(app(SlugService::class)->isReservedForDomain($domain, 'p/spring'));
    }

    public function test_path_must_be_unique_across_the_instance(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $this->linkPage($otherWorkspace, ['path' => 'spring']);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('link-pages.store'), ['name' => 'Spring', 'path' => 'spring'])
            ->assertSessionHasErrors('path');

        $this->assertSame(1, LinkPage::query()->count());
    }

    public function test_path_used_by_an_existing_application_host_short_link_is_rejected(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('localhost');
        $this->shortLink($workspace, $domain, 'p/legacy');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('link-pages.store'), ['name' => 'Legacy', 'path' => 'legacy'])
            ->assertSessionHasErrors('path');
    }

    public function test_no_other_route_lives_under_the_page_prefix(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => $uri === LinkPage::PATH_PREFIX || str_starts_with($uri, LinkPage::PATH_PREFIX.'/'))
            ->values()
            ->all();

        $this->assertSame(['p/{path}'], $routes);
    }
}
