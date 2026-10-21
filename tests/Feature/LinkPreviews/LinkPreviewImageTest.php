<?php

namespace Tests\Feature\LinkPreviews;

use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class LinkPreviewImageTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const SLACKBOT = 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_public_link_preview_image_is_served_on_its_domain_and_referenced_by_metadata(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch');
        $url = 'https://go.example.test/_preview/'.basename($link->preview_image_path);

        $this->withHeader('User-Agent', self::SLACKBOT)
            ->get('http://go.example.test/launch')
            ->assertSee('<meta property="og:image" content="'.$url.'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(0, $link->fresh()->successful_visits);
    }

    public function test_preview_images_of_protected_or_unavailable_links_are_not_served(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $protected = $this->linkWithImage($workspace, $domain, 'protected', ['password_hash' => Hash::make('secret')]);
        $disabled = $this->linkWithImage($workspace, $domain, 'disabled', ['is_enabled' => false]);

        foreach ([$protected, $disabled] as $link) {
            $this->get('https://go.example.test/_preview/'.basename($link->preview_image_path))->assertNotFound();
        }
    }

    public function test_preview_images_are_only_served_on_their_own_domain_and_for_referenced_files(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch');
        $this->shortLink($other, $otherDomain, 'other');
        $orphan = 'link-previews/'.str_repeat('c', 40).'.jpg';
        Storage::put($orphan, 'orphan');
        Storage::put('qr-logos/private.png', 'private');

        $this->get('https://other.example.test/_preview/'.basename($link->preview_image_path))->assertNotFound();
        $this->get('https://go.example.test/_preview/'.basename($orphan))->assertNotFound();
        $this->get('https://go.example.test/_preview/..%2Fqr-logos%2Fprivate.png')->assertNotFound();
        $this->get('https://go.example.test/_preview/private.png')->assertNotFound();
    }

    private function linkWithImage($workspace, $domain, string $slug, array $attributes = []): ShortLink
    {
        $path = 'link-previews/'.str()->random(40).'.jpg';
        Storage::put($path, UploadedFile::fake()->image('cover.jpg', 1200, 630)->getContent());

        return $this->shortLink($workspace, $domain, $slug, ['preview_image_path' => $path, ...$attributes]);
    }
}
