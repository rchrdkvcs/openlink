<?php

namespace Tests\Feature;

use App\Models\BioPage;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioPageFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_edit_and_publish_a_bio_page_without_leaking_later_draft_changes(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();

        $this->actingAs($owner)->post(route('bio-pages.store'), $this->payload($domain, 'alice'))
            ->assertRedirect();

        $bioPage = BioPage::query()->sole();

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))->assertRedirect();

        $this->get('http://'.$domain->hostname.'/alice')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/BioPage')
                ->where('bioPage.displayName', 'Alice')
                ->where('bioPage.biography', 'First biography')
                ->has('bioPage.elements', 1));

        $this->actingAs($owner)->patch(route('bio-pages.update', $bioPage), $this->payload($domain, 'alice', 'Draft biography'))
            ->assertRedirect();

        $this->get('http://'.$domain->hostname.'/alice')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('bioPage.biography', 'First biography'));

        $this->actingAs($owner)->get(route('bio-pages.show', $bioPage))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BioPages/Edit')
                ->where('bioPage.draft.biography', 'Draft biography')
                ->where('bioPage.published.biography', 'First biography'));
    }

    public function test_editor_can_create_and_edit_but_cannot_publish_unpublish_or_delete(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $editor = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $editor->id, 'role' => WorkspaceMember::ROLE_EDITOR]);

        $this->actingAs($editor)->post(route('bio-pages.store'), $this->payload($domain, 'editor'))
            ->assertRedirect();

        $bioPage = BioPage::query()->sole();

        $this->actingAs($editor)->patch(route('bio-pages.update', $bioPage), $this->payload($domain, 'editor', 'Edited'))
            ->assertRedirect();
        $this->actingAs($editor)->post(route('bio-pages.publish', $bioPage))->assertForbidden();
        $this->actingAs($editor)->post(route('bio-pages.unpublish', $bioPage))->assertForbidden();
        $this->actingAs($editor)->delete(route('bio-pages.destroy', $bioPage))->assertForbidden();

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))->assertRedirect();
    }

    public function test_viewer_can_view_workspace_bio_pages_but_cannot_change_them(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => WorkspaceMember::ROLE_VIEWER]);
        $bioPage = $this->createBioPage($workspace, $domain);

        $this->actingAs($viewer)->get(route('bio-pages.index'))->assertOk();
        $this->actingAs($viewer)->get(route('bio-pages.show', $bioPage))->assertOk();
        $this->actingAs($viewer)->patch(route('bio-pages.update', $bioPage), $this->payload($domain, 'alice'))->assertForbidden();
        $this->actingAs($viewer)->post(route('bio-pages.store'), $this->payload($domain, 'viewer'))->assertForbidden();
    }

    public function test_member_cannot_access_a_bio_page_from_another_workspace(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        [, , $otherOwner] = $this->workspaceAndDomain('other.example.test');
        $bioPage = $this->createBioPage($workspace, $domain);

        $this->actingAs($otherOwner)->get(route('bio-pages.show', $bioPage))->assertForbidden();
        $this->actingAs($otherOwner)->patch(route('bio-pages.update', $bioPage), $this->payload($domain, 'alice'))->assertForbidden();
    }

    public function test_publish_requires_active_domain_display_name_and_a_visible_usable_element(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $domain->update(['status' => Domain::STATUS_DISABLED, 'disabled_at' => now()]);

        $this->actingAs($owner)->post(route('bio-pages.store'), $this->payload($domain, 'alice'))->assertRedirect();
        $bioPage = BioPage::query()->sole();

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))
            ->assertSessionHasErrors('domainId');

        $domain->update(['status' => Domain::STATUS_ACTIVE, 'disabled_at' => null]);
        $payload = $this->payload($domain, 'alice');
        $payload['elements'][0]['visible'] = false;
        $this->actingAs($owner)->patch(route('bio-pages.update', $bioPage), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))
            ->assertSessionHasErrors('elements');
    }

    public function test_publish_rejects_critical_theme_contrast_failures(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $payload = $this->payload($domain, 'alice');
        $payload['theme'] = [
            'backgroundColor' => '#ffffff',
            'textColor' => '#fefefe',
            'destinationColor' => '#111111',
            'destinationTextColor' => '#121212',
        ];

        $this->actingAs($owner)->post(route('bio-pages.store'), $payload)->assertRedirect();

        $this->actingAs($owner)->post(route('bio-pages.publish', BioPage::query()->sole()))
            ->assertSessionHasErrors(['theme.textColor', 'theme.destinationTextColor']);
    }

    public function test_incomplete_elements_can_autosave_but_cannot_publish(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $bioPage = $this->createBioPage($workspace, $domain);
        $payload = $this->payload($domain, 'alice');
        $payload['elements'][0]['url'] = '';

        $this->actingAs($owner)->patch(route('bio-pages.update', $bioPage), $payload)
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))
            ->assertSessionHasErrors('elements.0.url');
    }

    public function test_section_heading_is_limited_to_80_characters_while_short_text_allows_300(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $bioPage = $this->createBioPage($workspace, $domain);
        $payload = $this->payload($domain, 'alice');
        $payload['elements'][0]['type'] = 'heading';
        $payload['elements'][0]['text'] = str_repeat('A', 81);

        $this->actingAs($owner)->patch(route('bio-pages.update', $bioPage), $payload)
            ->assertSessionHasErrors('elements.0.text');

        $payload['elements'][0]['type'] = 'text';
        $payload['elements'][0]['text'] = str_repeat('A', 300);

        $this->actingAs($owner)->patch(route('bio-pages.update', $bioPage), $payload)
            ->assertSessionDoesntHaveErrors();
    }

    public function test_outline_and_transparent_destination_contrast_is_checked_against_page_background(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();

        foreach (['outline', 'transparent'] as $index => $style) {
            $payload = $this->payload($domain, 'contrast-'.$index);
            $payload['theme'] = [
                'backgroundColor' => '#ffffff',
                'textColor' => '#17171c',
                'destinationColor' => '#17171c',
                'destinationTextColor' => '#17171c',
                'destinationStyle' => $style,
            ];

            $this->actingAs($owner)->post(route('bio-pages.store'), $payload)->assertRedirect();
            $bioPage = BioPage::query()->where('draft_slug', 'contrast-'.$index)->sole();
            $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))
                ->assertSessionDoesntHaveErrors('theme.destinationTextColor');
        }
    }

    public function test_editor_presence_is_reported_without_locking_other_members(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $admin = User::factory()->create(['email_verified_at' => now(), 'name' => 'Ada Admin']);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $admin->id, 'role' => WorkspaceMember::ROLE_ADMIN]);
        $bioPage = $this->createBioPage($workspace, $domain);

        $this->actingAs($owner)->get(route('bio-pages.show', $bioPage))->assertOk();

        $this->actingAs($admin)->get(route('bio-pages.show', $bioPage))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('activeEditors', 1)
                ->where('activeEditors.0', $owner->name));

        $this->actingAs($admin)->postJson(route('bio-pages.presence', $bioPage))
            ->assertOk()
            ->assertJsonPath('activeEditors.0', $owner->name);

        $this->actingAs($admin)->patch(route('bio-pages.update', $bioPage), ['biography' => 'Last write wins'])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_changing_bio_url_takes_effect_atomically_at_publish(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $bioPage = $this->createBioPage($workspace, $domain);

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))->assertRedirect();
        $this->actingAs($owner)->patch(route('bio-pages.update', $bioPage), $this->payload($domain, 'new-alice'))->assertRedirect();

        $this->get('http://'.$domain->hostname.'/alice')->assertOk();
        $this->get('http://'.$domain->hostname.'/new-alice')->assertNotFound();

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))->assertRedirect();

        $this->get('http://'.$domain->hostname.'/alice')->assertNotFound();
        $this->get('http://'.$domain->hostname.'/new-alice')->assertOk();
    }

    public function test_unpublish_keeps_the_draft_and_reserved_slug_but_removes_public_availability(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $bioPage = $this->createBioPage($workspace, $domain);
        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage));

        $this->actingAs($owner)->post(route('bio-pages.unpublish', $bioPage))->assertRedirect();

        $this->get('http://'.$domain->hostname.'/alice')->assertNotFound();
        $this->actingAs($owner)->get(route('bio-pages.show', $bioPage))
            ->assertInertia(fn ($page) => $page
                ->where('bioPage.draft.slug', 'alice')
                ->where('bioPage.status', 'draft'));

        $this->actingAs($owner)->post(route('short-links.store'), [
            'domain_id' => $domain->id,
            'slug' => 'alice',
            'destination_url' => 'https://example.com',
        ])->assertSessionHasErrors('slug');
    }

    public function test_short_urls_and_bio_urls_share_the_domain_and_slug_namespace(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'taken',
            'destination_url' => 'https://example.com',
        ]);

        $this->actingAs($owner)->post(route('bio-pages.store'), $this->payload($domain, 'taken'))
            ->assertSessionHasErrors('slug');

        $bioPage = $this->createBioPage($workspace, $domain);
        $this->actingAs($owner)->post(route('short-links.store'), [
            'domain_id' => $domain->id,
            'slug' => $bioPage->draft_slug,
            'destination_url' => 'https://example.com',
        ])->assertSessionHasErrors('slug');
    }

    public function test_permanent_delete_releases_the_bio_url(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceAndDomain();
        $bioPage = $this->createBioPage($workspace, $domain);

        $this->actingAs($owner)->delete(route('bio-pages.destroy', $bioPage))->assertRedirect(route('bio-pages.index'));
        $this->actingAs($owner)->post(route('short-links.store'), [
            'domain_id' => $domain->id,
            'slug' => 'alice',
            'destination_url' => 'https://example.com',
        ])->assertSessionDoesntHaveErrors('slug');
    }

    /** @return array{Workspace, Domain, User} */
    private function workspaceAndDomain(string $hostname = 'localhost'): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Acme', 'slug' => 'acme-'.str()->random(6)]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMember::ROLE_OWNER]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => str()->random(40),
            'verified_at' => now(),
            'dns_pointed_at' => now(),
        ]);

        return [$workspace, $domain, $owner];
    }

    private function createBioPage(Workspace $workspace, Domain $domain): BioPage
    {
        $bioPage = BioPage::create([
            'workspace_id' => $workspace->id,
            'draft_domain_id' => $domain->id,
            'draft_slug' => 'alice',
            'draft' => $this->draftData(),
        ]);
        $bioPage->elements()->create([
            'client_id' => 'website',
            'position' => 0,
            'draft' => $this->elementData(),
        ]);

        return $bioPage;
    }

    /** @return array<string, mixed> */
    private function payload(Domain $domain, string $slug, string $biography = 'First biography'): array
    {
        return [
            'domainId' => $domain->id,
            'slug' => $slug,
            'displayName' => 'Alice',
            'publicHandle' => '@alice',
            'biography' => $biography,
            'elements' => [$this->elementData()],
        ];
    }

    /** @return array<string, mixed> */
    private function draftData(): array
    {
        return [
            'displayName' => 'Alice',
            'publicHandle' => '@alice',
            'biography' => 'First biography',
            'profileImagePath' => null,
            'backgroundImagePath' => null,
            'theme' => [],
            'shareTitle' => null,
            'shareDescription' => null,
            'isIndexable' => true,
            'showBranding' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function elementData(): array
    {
        return [
            'clientId' => 'website',
            'type' => 'destination',
            'label' => 'Website',
            'sourceType' => 'external',
            'url' => 'https://example.com/alice',
            'shortLinkId' => null,
            'socialService' => null,
            'presentation' => 'button',
            'text' => null,
            'visible' => true,
            'openInNewTab' => false,
        ];
    }
}
