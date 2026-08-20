<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\BioElement;
use App\Models\BioPage;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioPageAnalyticsAndQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['openlink.analytics.via_queue' => true]);
    }

    public function test_loading_a_published_bio_page_records_a_bio_view(): void
    {
        [$bioPage, , $domain] = $this->publishedBioPage();

        $this->assertTrue($bioPage->isPublished());
        $this->assertSame($domain->id, $bioPage->published_domain_id);
        $this->assertSame('alice', $bioPage->published_slug);

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Version/17.5 Mobile Safari/604.1',
            'Referer' => 'https://www.instagram.com/alice',
        ])->get('http://'.$domain->hostname.'/alice')->assertOk();

        $this->assertDatabaseHas('analytics_events', [
            'bio_page_id' => $bioPage->id,
            'bio_element_id' => null,
            'metric' => 'bio_view',
            'outcome' => 'success',
            'referrer_host' => 'instagram.com',
        ]);
    }

    public function test_following_an_external_destination_records_a_bio_activation_and_redirects(): void
    {
        [$bioPage, $element] = $this->publishedBioPage();

        $this->get(route('public.bio.activate', [$bioPage, $element]))
            ->assertRedirect('https://example.com/alice');

        $event = AnalyticsEvent::query()->sole();
        $this->assertSame('bio_activation', $event->metric);
        $this->assertSame($bioPage->id, $event->bio_page_id);
        $this->assertSame($element->id, $event->bio_element_id);
        $this->assertNull($event->short_link_id);
    }

    public function test_following_a_short_link_destination_keeps_bio_activation_and_visit_distinct(): void
    {
        [$bioPage, $element, $domain] = $this->publishedBioPage();
        $shortLink = ShortLink::create([
            'workspace_id' => $bioPage->workspace_id,
            'domain_id' => $domain->id,
            'slug' => 'portfolio',
            'destination_url' => 'https://example.com/portfolio',
        ]);
        $published = $element->published;
        $published['sourceType'] = 'short_link';
        $published['shortLinkId'] = $shortLink->id;
        $published['url'] = null;
        $element->update(['published' => $published]);

        $shortUrl = 'https://'.$domain->hostname.'/portfolio';
        $this->get(route('public.bio.activate', [$bioPage, $element]))->assertRedirect($shortUrl);
        $this->assertSame(['bio_activation'], AnalyticsEvent::query()->pluck('metric')->all());
        $this->get('http://'.$domain->hostname.'/portfolio')->assertRedirect('https://example.com/portfolio');

        $this->assertSame(
            ['bio_activation', 'visit'],
            AnalyticsEvent::query()->orderBy('id')->pluck('metric')->all(),
        );
    }

    public function test_bio_page_dashboard_aggregates_views_activations_and_destinations(): void
    {
        [$bioPage, $element, $domain, $owner] = $this->publishedBioPage();
        $this->withHeader('User-Agent', 'Mozilla/5.0 AppleWebKit/537.36 Chrome/126.0 Safari/537.36');
        $this->get('http://'.$domain->hostname.'/alice');
        $this->get(route('public.bio.activate', [$bioPage, $element]));
        $this->get(route('public.bio.activate', [$bioPage, $element]));

        $this->actingAs($owner)
            ->withHeader('Host', 'localhost')
            ->get(route('bio-pages.analytics', $bioPage))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BioPages/Analytics')
                ->where('report.summary.bio_views', 1)
                ->where('report.summary.bio_activations', 2)
                ->where('report.top_bio_elements.0.id', $element->id)
                ->where('report.top_bio_elements.0.activations', 2));
    }

    public function test_bio_page_qr_code_can_be_created_and_resolves_as_scan_then_bio_view(): void
    {
        [$bioPage, , $domain, $owner] = $this->publishedBioPage();

        $this->actingAs($owner)
            ->post(route('bio-pages.qr-codes.store', $bioPage), ['name' => 'Profile card'])
            ->assertRedirect();

        $qrCode = $bioPage->qrCodes()->sole();
        $this->assertNull($qrCode->short_link_id);
        $this->assertSame($bioPage->workspace_id, $qrCode->workspace_id);
        $this->assertSame('https://'.$domain->hostname.'/qr/'.$qrCode->token, $qrCode->publicUrl());

        $this->actingAs($owner)
            ->withHeader('Host', 'localhost')
            ->get(route('bio-pages.show', $bioPage))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('qrCodes.0.token', $qrCode->token)
                ->where('qrCodes.0.name', 'Profile card')
                ->where('qrCodes.0.public_url', 'https://'.$domain->hostname.'/qr/'.$qrCode->token));

        $this->get('http://'.$domain->hostname.'/qr/'.$qrCode->token)
            ->assertRedirect('https://'.$domain->hostname.'/alice');

        $this->get('http://'.$domain->hostname.'/alice')->assertOk();

        $this->assertSame(
            ['scan', 'bio_view'],
            AnalyticsEvent::query()->orderBy('id')->pluck('metric')->all(),
        );
        $this->assertSame($qrCode->id, AnalyticsEvent::query()->where('metric', 'scan')->value('qr_code_id'));
    }

    /** @return array{BioPage, BioElement, Domain, User} */
    private function publishedBioPage(array $element = []): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Acme', 'slug' => 'acme-'.str()->random(6)]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMember::ROLE_OWNER]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'bio.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => str()->random(40),
            'verified_at' => now(),
            'dns_pointed_at' => now(),
        ]);
        $bioPage = BioPage::create([
            'workspace_id' => $workspace->id,
            'draft_domain_id' => $domain->id,
            'draft_slug' => 'alice',
            'draft' => [
                'displayName' => 'Alice',
                'publicHandle' => '@alice',
                'biography' => 'Hello',
                'profileImagePath' => null,
                'backgroundImagePath' => null,
                'theme' => [],
                'shareTitle' => null,
                'shareDescription' => null,
                'isIndexable' => true,
                'showBranding' => true,
            ],
        ]);
        $bioElement = $bioPage->elements()->create([
            'client_id' => 'website',
            'position' => 0,
            'draft' => [
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
                ...$element,
            ],
        ]);

        $this->actingAs($owner)->post(route('bio-pages.publish', $bioPage))->assertRedirect();

        return [$bioPage->fresh(), $bioElement->fresh(), $domain, $owner];
    }
}
