<?php

namespace App\Services\LinkPreviews;

use App\Models\ShortLink;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class LinkPreviewImageStorage
{
    public const DIRECTORY = 'link-previews';

    public const FILENAME_PATTERN = '[A-Za-z0-9]{40}\.jpg';

    private const MAX_WIDTH = 1200;

    private const QUALITY = 85;

    public const MAX_PIXELS = 4096 * 4096;

    public function store(UploadedFile $upload): string
    {
        $size = @getimagesize($upload->getRealPath());
        $fits = $size !== false && $size[0] > 0 && $size[1] > 0 && $size[0] * $size[1] <= self::MAX_PIXELS;
        $source = $fits ? @imagecreatefromstring((string) file_get_contents($upload->getRealPath())) : false;

        if (! $source) {
            throw ValidationException::withMessages(['preview_image' => __('openlink.validation.preview_image_unreadable')]);
        }

        $path = self::DIRECTORY.'/'.Str::random(40).'.jpg';

        if (! Storage::put($path, $this->encode($source))) {
            throw new RuntimeException('Unable to store the link preview image.');
        }

        return $path;
    }

    public function pathFor(string $filename): ?string
    {
        return preg_match('/^'.self::FILENAME_PATTERN.'$/', $filename) ? self::DIRECTORY.'/'.$filename : null;
    }

    public function response(string $path, string $cacheControl): Response
    {
        abort_unless(Storage::exists($path), 404);

        return response(Storage::get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $cacheControl,
            'Content-Security-Policy' => "default-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteUnreferenced(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, fn ($path) => is_string($path) && $path !== '')));

        if ($paths === []) {
            return;
        }

        $referenced = ShortLink::query()->whereIn('preview_image_path', $paths)->pluck('preview_image_path')->all();
        $unreferenced = array_values(array_diff($paths, $referenced));

        if ($unreferenced !== []) {
            Storage::delete($unreferenced);
        }
    }

    private function encode(GdImage $source): string
    {
        $width = min(self::MAX_WIDTH, imagesx($source));
        $height = max(1, (int) round(imagesy($source) * $width / imagesx($source)));
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
