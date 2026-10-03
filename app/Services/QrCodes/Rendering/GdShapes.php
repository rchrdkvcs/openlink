<?php

namespace App\Services\QrCodes\Rendering;

use GdImage;

class GdShapes
{
    public static function color(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    public static function roundedRect(GdImage $image, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        $r = min($r, intdiv($x1 - $x0, 2), intdiv($y1 - $y0, 2));

        if ($r <= 0) {
            imagefilledrectangle($image, $x0, $y0, $x1, $y1, $color);

            return;
        }

        imagefilledrectangle($image, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledrectangle($image, $x0, $y0 + $r, $x1, $y1 - $r, $color);

        foreach ([[$x0 + $r, $y0 + $r], [$x1 - $r, $y0 + $r], [$x0 + $r, $y1 - $r], [$x1 - $r, $y1 - $r]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, 2 * $r, 2 * $r, $color);
        }
    }

    public static function eyeShape(GdImage $image, string $style, float $x, float $y, float $side, int $color): void
    {
        $x0 = (int) round($x);
        $y0 = (int) round($y);
        $x1 = (int) round($x + $side) - 1;
        $y1 = (int) round($y + $side) - 1;

        match ($style) {
            'rounded' => self::roundedRect($image, $x0, $y0, $x1, $y1, (int) round($side * 0.3), $color),
            'circle' => imagefilledellipse($image, (int) round($x + $side / 2), (int) round($y + $side / 2), (int) round($side), (int) round($side), $color),
            default => imagefilledrectangle($image, $x0, $y0, $x1, $y1, $color),
        };
    }
}
