<?php

namespace Tests\Feature\LinkPreviews;

use App\Models\ShortLink;
use App\Models\WorkspaceMember;
use App\Services\LinkPreviews\LinkPreviewImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class LinkPreviewEditingTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_editor_saves_link_preview_text_and_a_reencoded_image(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.update', $link), [
                ...$this->updatePayload(),
                '_method' => 'patch',
                'preview_title' => '  Spring   launch ',
                'preview_description' => "Everything new\nthis season.",
                'preview_image' => UploadedFile::fake()->image('cover.png', 2400, 1260),
            ])
            ->assertSessionHasNoErrors();

        $link->refresh();
        $this->assertSame('Spring launch', $link->preview_title);
        $this->assertSame('Everything new this season.', $link->preview_description);
        $this->assertMatchesRegularExpression('#^link-previews/[A-Za-z0-9]{40}\.jpg$#', $link->preview_image_path);
        Storage::assertExists($link->preview_image_path);

        $image = imagecreatefromstring(Storage::get($link->preview_image_path));
        $this->assertSame(1200, imagesx($image));
        $this->assertSame(630, imagesy($image));
        $this->assertSame('image/jpeg', getimagesizefromstring(Storage::get($link->preview_image_path))['mime']);
    }

    public function test_link_preview_text_rejects_markup_and_overlong_values(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch');

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $link), [
                ...$this->updatePayload(),
                'preview_title' => '<script>alert(1)</script>',
                'preview_description' => str_repeat('a', 301),
            ])
            ->assertSessionHasErrors(['preview_title', 'preview_description']);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $link), [
                ...$this->updatePayload(),
                'preview_title' => 'Launch <img src=x onerror=alert(1)>',
            ])
            ->assertSessionHasErrors('preview_title');

        $this->assertNull($link->fresh()->preview_title);
    }

    public function test_unsafe_preview_images_are_rejected(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch');
        $svg = UploadedFile::fake()->createWithContent('cover.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $disguised = UploadedFile::fake()->createWithContent('cover.png', '<html><script>alert(1)</script></html>');
        $tiny = UploadedFile::fake()->image('cover.png', 50, 50);

        foreach ([$svg, $disguised, $tiny] as $upload) {
            $this->actingInWorkspace($owner, $workspace)
                ->post(route('short-links.update', $link), [...$this->updatePayload(), '_method' => 'patch', 'preview_image' => $upload])
                ->assertSessionHasErrors('preview_image');
        }

        $this->assertNull($link->fresh()->preview_image_path);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_viewers_cannot_change_link_previews(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->shortLink($workspace, $domain, 'launch');
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingInWorkspace($viewer, $workspace)
            ->post(route('short-links.update', $link), [
                ...$this->updatePayload(),
                '_method' => 'patch',
                'preview_title' => 'Hijacked',
                'preview_image' => UploadedFile::fake()->image('cover.png', 1200, 630),
            ])
            ->assertForbidden();

        $this->assertNull($link->fresh()->preview_title);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_links_of_another_workspace_cannot_be_edited(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain('go.example.test');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->linkWithImage($other, $otherDomain, 'foreign');

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $foreign), [...$this->updatePayload(), 'remove_preview_image' => true])
            ->assertForbidden();

        Storage::assertExists($foreign->preview_image_path);
    }

    public function test_omitted_link_preview_fields_are_kept_when_updating_through_the_api(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch', ['preview_title' => 'Spring launch']);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $link), $this->updatePayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('Spring launch', $link->fresh()->preview_title);
        Storage::assertExists($link->preview_image_path);
    }

    public function test_replacing_and_removing_the_image_cleans_up_only_the_previous_asset(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch');
        $sibling = $this->linkWithImage($workspace, $domain, 'sibling');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->linkWithImage($other, $otherDomain, 'foreign');
        $original = $link->preview_image_path;

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('short-links.update', $link), [
                ...$this->updatePayload(),
                '_method' => 'patch',
                'preview_image' => UploadedFile::fake()->image('next.jpg', 1200, 630),
            ])
            ->assertSessionHasNoErrors();

        $replacement = $link->fresh()->preview_image_path;
        $this->assertNotSame($original, $replacement);
        Storage::assertMissing($original);
        Storage::assertExists($replacement);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('short-links.update', $link), [...$this->updatePayload(), 'remove_preview_image' => true])
            ->assertSessionHasNoErrors();

        $this->assertNull($link->fresh()->preview_image_path);
        Storage::assertMissing($replacement);
        Storage::assertExists($sibling->preview_image_path);
        Storage::assertExists($foreign->preview_image_path);
    }

    public function test_cleanup_keeps_assets_that_a_link_still_references(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch');
        $orphan = 'link-previews/'.str_repeat('d', 40).'.jpg';
        Storage::put($orphan, 'orphan');

        app(LinkPreviewImageStorage::class)->deleteUnreferenced([$link->preview_image_path, $orphan]);

        Storage::assertExists($link->preview_image_path);
        Storage::assertMissing($orphan);
    }

    public function test_deleting_links_workspaces_and_domains_removes_their_preview_images_only(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $deleted = $this->linkWithImage($workspace, $domain, 'deleted');
        $kept = $this->linkWithImage($workspace, $domain, 'kept');
        [$other, $otherDomain] = $this->workspaceWithActiveDomain('other.example.test');
        $foreign = $this->linkWithImage($other, $otherDomain, 'foreign');
        [$third, $thirdDomain] = $this->workspaceWithActiveDomain('third.example.test');
        $domainLink = $this->linkWithImage($third, $thirdDomain, 'domain');

        $this->actingInWorkspace($owner, $workspace)->delete(route('short-links.destroy', $deleted));
        Storage::assertMissing($deleted->preview_image_path);
        Storage::assertExists($kept->preview_image_path);

        $workspace->delete();
        Storage::assertMissing($kept->preview_image_path);
        Storage::assertExists($foreign->preview_image_path);

        $thirdDomain->delete();
        Storage::assertMissing($domainLink->preview_image_path);
        Storage::assertExists($foreign->preview_image_path);
    }

    public function test_members_can_view_the_preview_image_of_their_workspace_links_only(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch', ['password_hash' => bcrypt('secret')]);
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        [$other, , $outsider] = $this->workspaceWithActiveDomain('other.example.test');

        $this->actingInWorkspace($viewer, $workspace)
            ->get(route('short-links.preview-image', $link))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingInWorkspace($outsider, $other)
            ->get(route('short-links.preview-image', $link))
            ->assertForbidden();
    }

    public function test_link_payload_exposes_the_link_preview(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.example.test');
        $link = $this->linkWithImage($workspace, $domain, 'launch', ['preview_title' => 'Spring launch']);

        $this->actingInWorkspace($owner, $workspace)
            ->getJson('/api/v1/links/'.$link->id)
            ->assertOk()
            ->assertJsonPath('data.preview.title', 'Spring launch')
            ->assertJsonPath('data.preview.description', null)
            ->assertJsonPath('data.preview.image_url', route('short-links.preview-image', ['shortLink' => $link, 'v' => basename($link->preview_image_path, '.jpg')]));
    }

    private function linkWithImage($workspace, $domain, string $slug, array $attributes = []): ShortLink
    {
        $path = 'link-previews/'.str()->random(40).'.jpg';
        Storage::put($path, UploadedFile::fake()->image('cover.jpg', 1200, 630)->getContent());

        return $this->shortLink($workspace, $domain, $slug, ['preview_image_path' => $path, ...$attributes]);
    }

    private function updatePayload(): array
    {
        return ['destination_url' => 'https://example.com/landing', 'is_enabled' => true];
    }
}
