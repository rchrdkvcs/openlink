<?php

namespace Tests\Feature\LinkPages;

use App\Enums\DomainStatus;
use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\LinkPageItem;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesLinkPages;
use Tests\TestCase;

class PublicLinkPageTest extends TestCase
{
    use CreatesLinkPages;
    use RefreshDatabase;

    public function test_published_link_page_shows_only_its_public_content(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $tickets = $this->shortLink($workspace, $domain, 'tickets', ['destination_url' => 'https://tickets.example.com/private']);
        $schedule = $this->shortLink($workspace, $domain, 'schedule');
        $this->publishedLinkPage($workspace, [], [[$tickets, 'Buy tickets'], $schedule]);

        $this->get('/p/spring')
            ->assertOk()
            ->assertDontSee('tickets.example.com')
            ->assertDontSee($workspace->name)
            ->assertInertia(fn ($page) => $page
                ->component('Public/LinkPage')
                ->where('page', [
                    'title' => 'Spring conference',
                    'display' => ['show_icons' => true, 'show_labels' => true, 'show_urls' => false],
                    'description' => 'Everything for the event.',
                    'logo' => null,
                    'links' => [
                        ['label' => 'Buy tickets', 'url' => 'https://go.example.test/tickets', 'icon' => null],
                        ['label' => 'go.example.test/schedule', 'url' => 'https://go.example.test/schedule', 'icon' => null],
                    ],
                ]));
    }

    public function test_draft_link_page_is_not_public(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $this->linkPage($workspace, ['title' => 'Secret launch']);

        $this->get('/p/spring')
            ->assertNotFound()
            ->assertDontSee('Secret launch')
            ->assertInertia(fn ($page) => $page->component('Public/Unavailable'));
    }

    public function test_link_page_path_is_matched_without_regard_to_case(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $this->publishedLinkPage($workspace);

        $this->get('/p/Spring')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/LinkPage'));
    }

    public function test_unknown_link_page_is_neutral(): void
    {
        $this->get('/p/missing')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Public/Unavailable'));
    }

    public function test_unavailable_links_are_left_out_without_explanation(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $pending = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'pending.example.test',
            'status' => DomainStatus::Pending,
            'verification_token' => 'pending-token',
        ]);
        [$otherWorkspace, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $links = [
            $this->shortLink($workspace, $domain, 'active'),
            $this->shortLink($workspace, $domain, 'archived', ['archived_at' => now()]),
            $this->shortLink($workspace, $domain, 'disabled', ['is_enabled' => false]),
            $this->shortLink($workspace, $domain, 'scheduled', ['activates_at' => now()->addDay()]),
            $this->shortLink($workspace, $domain, 'expired', ['expires_at' => now()->subDay()]),
            $this->shortLink($workspace, $domain, 'used-up', ['visit_limit' => 1, 'successful_visits' => 1]),
            $this->shortLink($workspace, $pending, 'pending-domain'),
            $this->shortLink($otherWorkspace, $otherDomain, 'foreign'),
        ];
        $this->publishedLinkPage($workspace, [], $links);

        $this->get('/p/spring')
            ->assertOk()
            ->assertDontSee('archived')
            ->assertDontSee('expired')
            ->assertInertia(fn ($page) => $page
                ->where('page.links', [['label' => 'go.example.test/active', 'url' => 'https://go.example.test/active', 'icon' => null]]));
    }

    public function test_deleted_short_links_are_removed_and_an_empty_page_stays_neutral(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'gone');
        $this->publishedLinkPage($workspace, [], [$link]);

        $link->delete();

        $this->assertSame(0, LinkPageItem::query()->count());
        $this->get('/p/spring')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('page.links', []));
    }

    public function test_protected_link_is_listed_without_revealing_its_destination_and_still_asks_for_password(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('localhost');
        $link = $this->shortLink($workspace, $domain, 'vip', [
            'destination_url' => 'https://secret.example.com/backstage',
            'password_hash' => Hash::make('opensesame'),
        ]);
        $this->publishedLinkPage($workspace, [], [[$link, 'Backstage']]);

        $this->get('/p/spring')
            ->assertOk()
            ->assertDontSee('secret.example.com')
            ->assertInertia(fn ($page) => $page->where('page.links.0.url', 'https://localhost/vip'));

        $this->get('/vip')->assertOk()->assertInertia(fn ($page) => $page->component('Public/Password'));
    }

    public function test_listed_link_click_through_uses_normal_resolution(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('localhost');
        $link = $this->shortLink($workspace, $domain, 'tickets', ['destination_url' => 'https://tickets.example.com']);
        $this->publishedLinkPage($workspace, [], [$link]);

        $this->get('/p/spring')->assertOk();
        $this->assertSame(0, AnalyticsEvent::query()->count());

        $this->get('/tickets')->assertRedirect('https://tickets.example.com');
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_text_content_is_never_rendered_as_html(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'safe');
        $this->publishedLinkPage($workspace, [
            'title' => '<script>alert(1)</script>',
            'description' => '<img src=x onerror=alert(2)>',
        ], [[$link, '<b onclick=alert(3)>Hi</b>']]);

        $this->get('/p/spring')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false)
            ->assertDontSee('<b onclick=alert(3)>', false)
            ->assertInertia(fn ($page) => $page
                ->where('page.title', '<script>alert(1)</script>')
                ->where('page.links.0.label', '<b onclick=alert(3)>Hi</b>'));
    }

    public function test_link_pages_are_only_served_on_the_application_host(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $this->publishedLinkPage($workspace);
        $this->shortLink($workspace, $domain, 'p/spring', ['destination_url' => 'https://example.com/custom-domain']);

        $this->get('http://go.example.test/p/spring')->assertRedirect('https://example.com/custom-domain');
    }

    public function test_existing_application_host_short_links_under_the_page_prefix_keep_resolving(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('localhost');
        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'p/legacy',
            'destination_url' => 'https://example.com/legacy',
        ]);

        $this->get('/p/legacy')->assertRedirect('https://example.com/legacy');
    }
}
