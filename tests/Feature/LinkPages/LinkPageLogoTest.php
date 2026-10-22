<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Services\LinkPages\LinkPageLogoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CreatesLinkPages;
use Tests\TestCase;

class LinkPageLogoTest extends TestCase
{
    use CreatesLinkPages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_logo_is_reencoded_as_a_small_png_without_appended_content(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $linkPage = $this->publishedLinkPage($workspace);
        $upload = $this->pngUpload(600, 300, '<script>alert(1)</script>');

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $upload]))
            ->assertSessionHasNoErrors();

        $linkPage->refresh();
        $stored = Storage::get($linkPage->logo_path);
        $this->assertStringEndsWith('.png', $linkPage->logo_path);
        $this->assertStringNotContainsString('<script>', $stored);
        [$width, $height, $type] = getimagesizefromstring($stored);
        $this->assertSame([256, 128, IMAGETYPE_PNG], [$width, $height, $type]);

        $this->get('/p/spring')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('page.logo', 'data:image/png;base64,'.base64_encode($stored)));
    }

    public function test_svg_and_non_image_uploads_are_rejected(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $linkPage = $this->linkPage($workspace);

        foreach ([
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('logo.png', 'not an image'),
        ] as $upload) {
            $this->actingInWorkspace($owner, $workspace)
                ->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $upload]))
                ->assertSessionHasErrors('logo');
        }

        $this->assertNull($linkPage->fresh()->logo_path);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_logo_is_removed_from_storage_when_replaced_removed_or_deleted(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $linkPage = $this->linkPage($workspace);
        $client = $this->actingInWorkspace($owner, $workspace);

        $client->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $this->pngUpload(40, 40)]));
        $first = $linkPage->fresh()->logo_path;
        $client->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $this->pngUpload(40, 40)]));
        $second = $linkPage->fresh()->logo_path;

        Storage::assertMissing($first);
        Storage::assertExists($second);

        $client->patch(route('link-pages.update', $linkPage), $this->content(['remove_logo' => true]));
        Storage::assertMissing($second);
        $this->assertNull($linkPage->fresh()->logo_path);

        $client->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $this->pngUpload(40, 40)]));
        $third = $linkPage->fresh()->logo_path;
        $client->delete(route('link-pages.destroy', $linkPage));

        Storage::assertMissing($third);
        $this->assertSame(0, LinkPage::query()->count());
    }

    public function test_images_with_too_many_pixels_are_rejected_before_decoding(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $linkPage = $this->linkPage($workspace);

        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $this->pngHeaderUpload(4096, 4096)]))
            ->assertSessionHasErrors(['logo' => 'Use an image of at most 2048 by 2048 pixels.']);

        $this->assertNull($linkPage->fresh()->logo_path);
        $this->assertSame([], Storage::allFiles());
    }

    public function test_previous_logo_survives_a_rolled_back_replacement(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $linkPage = $this->linkPage($workspace);
        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $this->pngUpload(40, 40)]));
        $previous = $linkPage->fresh()->logo_path;

        try {
            DB::transaction(function () use ($linkPage): void {
                app(LinkPageLogoStorage::class)->save($linkPage->fresh(), $this->pngUpload(40, 40));

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
        }

        Storage::assertExists($previous);
        $this->assertSame($previous, $linkPage->fresh()->logo_path);
    }

    public function test_logos_are_removed_when_their_workspace_is_deleted(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $linkPage = $this->linkPage($workspace);
        $this->actingInWorkspace($owner, $workspace)
            ->patch(route('link-pages.update', $linkPage), $this->content(['logo' => $this->pngUpload(40, 40)]));
        $path = $linkPage->fresh()->logo_path;

        $workspace->delete();

        Storage::assertMissing($path);
        $this->assertSame(0, LinkPage::query()->count());
    }

    private function content(array $overrides = []): array
    {
        return ['name' => 'Spring', 'path' => 'spring', 'title' => 'Spring', ...$overrides];
    }

    private function pngHeaderUpload(int $width, int $height): UploadedFile
    {
        $header = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
        $contents = "\x89PNG\r\n\x1a\n".pack('N', 13).$header.pack('N', crc32($header));

        return UploadedFile::fake()->createWithContent('logo.png', $contents);
    }

    private function pngUpload(int $width, int $height, string $appended = ''): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 200));
        ob_start();
        imagepng($image);
        $contents = ob_get_clean().$appended;

        return UploadedFile::fake()->createWithContent('logo.png', $contents);
    }
}
