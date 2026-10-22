<?php

namespace App\Services\LinkPages;

use App\Models\LinkPage;
use GdImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LinkPageLogoStorage
{
    public const MAX_DIMENSION = 256;

    public const MAX_SOURCE_PIXELS = 2048 * 2048;

    private const DIRECTORY = 'link-page-logos';

    public function save(LinkPage $linkPage, ?UploadedFile $upload = null, bool $remove = false): LinkPage
    {
        $previous = $linkPage->logo_path;
        $stored = $upload ? $this->store($upload) : null;

        if ($stored) {
            $linkPage->logo_path = $stored;
        } elseif ($remove) {
            $linkPage->logo_path = null;
        }

        try {
            if (! $linkPage->save()) {
                throw new RuntimeException('Unable to save the Link Page.');
            }
        } catch (Throwable $exception) {
            $this->delete([$stored]);

            throw $exception;
        }

        if ($previous !== $linkPage->logo_path) {
            DB::afterCommit(fn () => $this->delete([$previous]));
        }

        return $linkPage;
    }

    public function dataUri(LinkPage $linkPage): ?string
    {
        if (! $linkPage->hasLogo() || ! Storage::exists($linkPage->logo_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) Storage::get($linkPage->logo_path));
    }

    public function pathsOf(Builder $linkPages): array
    {
        return $linkPages->whereNotNull('logo_path')->pluck('logo_path')->all();
    }

    public function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));

        if ($paths !== []) {
            Storage::delete($paths);
        }
    }

    private function store(UploadedFile $upload): string
    {
        $path = self::DIRECTORY.'/'.Str::random(40).'.png';

        if (! Storage::put($path, $this->reencode($upload))) {
            throw new RuntimeException('Unable to store the Link Page logo.');
        }

        return $path;
    }

    private function reencode(UploadedFile $upload): string
    {
        $contents = (string) file_get_contents($upload->getRealPath());
        $size = @getimagesizefromstring($contents);

        if (! $size || $size[0] < 1 || $size[1] < 1) {
            throw ValidationException::withMessages(['logo' => __('validation.image', ['attribute' => 'logo'])]);
        }

        if ($size[0] * $size[1] > self::MAX_SOURCE_PIXELS) {
            throw ValidationException::withMessages(['logo' => __('openlink.validation.link_page_logo_too_large')]);
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['logo' => __('validation.image', ['attribute' => 'logo'])]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagepng($target, null, 9);

        return (string) ob_get_clean();
    }
}
