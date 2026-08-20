<?php

namespace App\Http\Controllers;

use App\Models\BioPage;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BioPageOpenGraphController extends Controller
{
    public function __invoke(BioPage $bioPage): Response
    {
        abort_unless($bioPage->isPublished() && $bioPage->published, 404);

        $version = $bioPage->published;
        $theme = $version['theme'] ?? [];
        $background = $this->rgb($theme['backgroundColor'] ?? '#17171c');
        $gradient = $this->rgb($theme['gradientColor'] ?? '#4f46e5');
        $foreground = $this->rgb($theme['textColor'] ?? '#f7f7f8');
        $canvas = imagecreatetruecolor(400, 210);

        for ($y = 0; $y < 210; $y++) {
            $ratio = ($theme['backgroundType'] ?? 'color') === 'gradient' ? $y / 209 : 0;
            $color = imagecolorallocate(
                $canvas,
                (int) round($background[0] + ($gradient[0] - $background[0]) * $ratio),
                (int) round($background[1] + ($gradient[1] - $background[1]) * $ratio),
                (int) round($background[2] + ($gradient[2] - $background[2]) * $ratio),
            );
            imageline($canvas, 0, $y, 400, $y, $color);
        }

        $textColor = imagecolorallocate($canvas, ...$foreground);
        $badgeColor = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledellipse($canvas, 200, 54, 58, 58, $badgeColor);

        $displayName = $this->plain($version['displayName'] ?? 'Bio Page', 38);
        $initials = collect(preg_split('/\s+/', $displayName) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => strtoupper($part[0]))
            ->join('');
        $initialColor = imagecolorallocate($canvas, ...$background);
        $this->centeredText($canvas, $initials ?: '?', 47, $initialColor, 5);
        $this->centeredText($canvas, $displayName, 92, $textColor, 5);

        $handle = $this->plain($version['publicHandle'] ?? '', 44);
        if ($handle !== '') {
            $this->centeredText($canvas, $handle, 116, $textColor, 3);
        }

        $biography = $this->plain($version['biography'] ?? '', 54);
        if ($biography !== '') {
            foreach (array_slice(explode("\n", wordwrap($biography, 52)), 0, 2) as $line => $text) {
                $this->centeredText($canvas, $text, 144 + $line * 17, $textColor, 3);
            }
        }

        imagestring($canvas, 2, 12, 192, 'OPENLINK', $textColor);
        $image = imagescale($canvas, 1200, 630, IMG_BICUBIC_FIXED) ?: $canvas;

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        if ($image !== $canvas) {
            imagedestroy($image);
        }
        imagedestroy($canvas);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** @return array{int, int, int} */
    private function rgb(string $color): array
    {
        if (! preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            $color = '#17171c';
        }

        return [hexdec(substr($color, 1, 2)), hexdec(substr($color, 3, 2)), hexdec(substr($color, 5, 2))];
    }

    private function plain(string $value, int $limit): string
    {
        return substr(Str::ascii(strip_tags($value)), 0, $limit);
    }

    private function centeredText(\GdImage $image, string $text, int $y, int $color, int $font): void
    {
        $x = max(8, (int) ((400 - imagefontwidth($font) * strlen($text)) / 2));
        imagestring($image, $font, $x, $y, $text, $color);
    }
}
