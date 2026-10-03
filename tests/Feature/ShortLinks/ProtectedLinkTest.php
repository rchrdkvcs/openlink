<?php

namespace Tests\Feature\ShortLinks;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ProtectedLinkTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_protected_link_password_flow_works_on_redirect_only_domain(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->protectedLink($workspace, $domain, 'secret');

        $this->get('http://'.$domain->hostname.'/secret')
            ->assertOk();

        $this->post('http://'.$domain->hostname.'/password/'.$link->id, ['password' => 'opensesame'])
            ->assertRedirect('https://example.com/secret');
    }

    public function test_protected_link_password_form_posts_to_current_host(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->protectedLink($workspace, $domain, 'host-bound-secret');

        $this->get('http://'.$domain->hostname.'/host-bound-secret')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Password')
                ->where('passwordUrl', '/password/'.$link->id));
    }

    public function test_protected_link_requires_password_before_visit_is_counted(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->protectedLink($workspace, $domain, 'secret');

        $this->withHeader('Host', 'localhost')->get('/secret')->assertOk();
        $this->assertSame(0, $link->fresh()->successful_visits);

        $this->post(route('public.password', $link), ['password' => 'wrong'])->assertStatus(403);
        $this->assertSame(0, $link->fresh()->successful_visits);

        $this->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertRedirect('https://example.com/secret');
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_inertia_password_submit_returns_location_for_external_redirect(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->protectedLink($workspace, $domain, 'secret-inertia');

        $this->withHeader('X-Inertia', 'true')
            ->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://example.com/secret-inertia');

        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_password_submit_resolves_known_link_even_when_post_host_differs(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain();
        $link = $this->protectedLink($workspace, $domain, 'secret-custom');

        $this->withHeader('Host', 'app.test')
            ->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertRedirect('https://example.com/secret-custom');

        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_link_password_can_be_removed_by_submitting_empty_password(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'public-again', [
            'destination_url' => 'https://example.com/public-again',
            'password_hash' => Hash::make('secret'),
        ]);

        $this->actingInWorkspace($user, $workspace)
            ->patch(route('short-links.update', $link), [
                'folder_id' => null,
                'destination_url' => 'https://example.com/public-again',
                'fallback_url' => null,
                'is_enabled' => true,
                'activates_at' => null,
                'expires_at' => null,
                'visit_limit' => null,
                'password' => null,
            ])->assertRedirect();

        $this->assertNull($link->fresh()->password_hash);
    }

    private function protectedLink(Workspace $workspace, Domain $domain, string $slug): ShortLink
    {
        return $this->shortLink($workspace, $domain, $slug, [
            'destination_url' => 'https://example.com/'.$slug,
            'password_hash' => Hash::make('opensesame'),
        ]);
    }
}
