<?php

namespace Tests\Feature\LinkPages;

use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesLinkPages;
use Tests\TestCase;

class LinkPageDisplayTest extends TestCase
{
    use CreatesLinkPages;
    use RefreshDatabase;

    public function test_editor_saves_and_reopens_display_settings_used_by_the_public_page(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'code', ['destination_url' => 'https://github.com/openlink/private?secret=abc']);
        $page = $this->publishedLinkPage($workspace, [], [[$link, 'Code']]);
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $display = ['show_icons' => true, 'show_labels' => false, 'show_urls' => false];
        $before = $link->fresh()->toArray();

        $this->actingInWorkspace($editor, $workspace)
            ->patch(route('link-pages.update', $page), [
                'name' => $page->name, 'path' => $page->path, 'title' => $page->title,
                'display' => $display,
                'items' => [['short_link_id' => $link->id, 'label' => 'Code']],
            ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($display, $page->fresh()->displaySettings());
        $this->assertSame($before, $link->fresh()->toArray());
        $this->get(route('link-pages.show', $page))->assertInertia(fn ($props) => $props
            ->where('linkPage.display', $display)
            ->where('linkPage.items.0.icon', 'github')
            ->where('shortLinks.0.icon', 'github'));

        $this->get('/p/spring')->assertInertia(fn ($props) => $props
            ->where('page.display', $display)
            ->where('page.links.0', ['label' => 'Code', 'url' => 'https://go.example.test/code', 'icon' => 'github']));

        $this->patch(route('link-pages.update', $page), [
            'name' => $page->name, 'path' => $page->path, 'title' => $page->title,
        ])->assertSessionHasNoErrors();
        $this->assertSame($display, $page->fresh()->displaySettings());
    }

    public function test_display_settings_cannot_hide_everything_or_accept_unknown_fields(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $page = $this->linkPage($workspace);
        $data = ['name' => $page->name, 'path' => $page->path, 'title' => $page->title];

        $this->actingInWorkspace($owner, $workspace)->patch(route('link-pages.update', $page), [
            ...$data,
            'display' => ['show_icons' => false, 'show_labels' => false, 'show_urls' => false],
        ])->assertSessionHasErrors('display');

        $this->patch(route('link-pages.update', $page), [
            ...$data,
            'display' => ['show_icons' => 'yes', 'show_labels' => true, 'show_urls' => false],
        ])->assertSessionHasErrors('display.show_icons');

        $this->patch(route('link-pages.update', $page), [
            ...$data,
            'display' => ['show_icons' => true, 'show_labels' => false, 'show_urls' => true, 'workspace_id' => 123],
        ])->assertSessionHasErrors('display');

        $this->patch(route('link-pages.update', $page), [
            ...$data, 'display' => ['show_icons' => true],
        ])->assertSessionHasErrors(['display.show_labels', 'display.show_urls']);
    }

    public function test_viewers_and_other_workspaces_cannot_change_display_settings(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $page = $this->linkPage($workspace);
        [$otherWorkspace] = $this->workspaceWithOwner('Other');
        $foreign = $this->linkPage($otherWorkspace, ['path' => 'foreign']);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $data = [
            'name' => $page->name, 'path' => $page->path, 'title' => $page->title,
            'display' => ['show_icons' => true, 'show_labels' => false, 'show_urls' => false],
        ];

        $this->actingInWorkspace($viewer, $workspace)->patch(route('link-pages.update', $page), $data)->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->patch(route('link-pages.update', $foreign), $data)->assertForbidden();
        $this->assertTrue($page->fresh()->show_labels);
        $this->assertTrue($foreign->fresh()->show_labels);
    }

    public function test_icons_are_local_and_never_reveal_protected_or_unknown_hosts(): void
    {
        Http::preventStrayRequests();
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $public = $this->shortLink($workspace, $domain, 'video', ['destination_url' => 'https://www.youtube.com/watch?v=secret']);
        $protected = $this->shortLink($workspace, $domain, 'private', [
            'destination_url' => 'https://github.com/secret-org/private?token=secret',
            'password_hash' => Hash::make('password'),
        ]);
        $unknown = $this->shortLink($workspace, $domain, 'other', ['destination_url' => 'https://internal.secret.example/path']);
        $spoofed = $this->shortLink($workspace, $domain, 'spoof', ['destination_url' => 'https://github.com.attacker.example/path']);
        $local = $this->shortLink($workspace, $domain, 'local', ['destination_url' => 'http://169.254.169.254/latest/meta-data']);
        $page = $this->publishedLinkPage($workspace, [], [$public, $protected, $unknown, $spoofed, $local]);

        $this->get('/p/spring')->assertOk()
            ->assertDontSee('youtube.com')->assertDontSee('github.com')->assertDontSee('secret.example')
            ->assertDontSee('169.254.169.254')->assertDontSee('token=secret')
            ->assertInertia(fn ($props) => $props
                ->where('page.links.0.icon', 'youtube')
                ->where('page.links.1.icon', null)
                ->where('page.links.2.icon', null)
                ->where('page.links.3.icon', null)
                ->where('page.links.4.icon', null));

        $this->actingInWorkspace($owner, $workspace)->get(route('link-pages.show', $page))->assertInertia(fn ($props) => $props
            ->where('linkPage.items.1.icon', null)
            ->where('shortLinks.3.icon', null));

        Http::assertNothingSent();

        $public->update(['password_hash' => Hash::make('new-password')]);
        $this->get('/p/spring')->assertInertia(fn ($props) => $props->where('page.links.0.icon', null));
    }
}
