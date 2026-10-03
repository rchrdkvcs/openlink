<?php

namespace Tests\Feature\ShortLinks;

use App\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RedirectOnlyDomainTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_slug_service_allows_application_reserved_slugs_on_redirect_only_domains(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $slugs = app(SlugService::class);

        $this->assertSame('dashboard', $slugs->validateCustom($domain, 'dashboard'));
        $this->assertSame('app/release', $slugs->validateCustom($domain, 'app/release'));
    }

    public function test_application_routes_render_only_on_application_domain(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.example.test');

        $this->get('http://localhost/login')
            ->assertOk();

        $this->get('http://'.$domain->hostname.'/login')
            ->assertStatus(404);
    }

    public function test_redirect_only_domain_resolves_application_route_names_as_slugs(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'login', ['destination_url' => 'https://example.com/customer-login']);

        $this->get('http://'.$domain->hostname.'/login')
            ->assertRedirect('https://example.com/customer-login');
    }

    public function test_redirect_only_domain_root_shows_neutral_unavailable_page(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.example.test');

        $this->get('http://'.$domain->hostname.'/')
            ->assertStatus(404);
    }

    public function test_redirect_only_domain_resolves_reserved_prefixes_as_slugs(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->shortLink($workspace, $domain, 'app/release', ['destination_url' => 'https://example.com/releases']);

        $this->get('http://'.$domain->hostname.'/app/release')
            ->assertRedirect('https://example.com/releases');
    }

    public function test_redirect_only_domain_does_not_render_authenticated_ui_routes(): void
    {
        [, $domain, $user] = $this->workspaceWithActiveDomain('go.example.test');

        foreach (['dashboard', 'links', 'domains', 'members', 'workspaces', 'settings', 'profile'] as $path) {
            $this->actingAs($user)
                ->get('http://'.$domain->hostname.'/'.$path)
                ->assertStatus(404);
        }
    }
}
