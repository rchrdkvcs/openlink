<?php

namespace App\Services\QrCodes\Rendering;

use App\Models\QrCode;
use GdImage;

class GdQrRenderer
{
    public function render(QrCode $qrCode, QrGrid $grid, ?string $logo): string
    {
        $size = $grid->size;
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);

        $fg = GdShapes::color($image, $qrCode->foreground_color);
        $hole = $this->paintBackground($image, $qrCode, $size);

        $this->paintModules($image, $qrCode, $grid, $fg);

        foreach ($grid->finderPositions() as [$x, $y]) {
            $this->paintEye($image, $qrCode, $x, $y, $grid->moduleSize, $fg, $hole);
        }

        if ($logo !== null) {
            $this->paintLogo($image, $qrCode, $logo, $size, $grid->moduleSize);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function paintBackground(GdImage $image, QrCode $qrCode, int $size): int
    {
        if (! $qrCode->background_transparent) {
            $background = GdShapes::color($image, $qrCode->background_color);
            imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $background);

            return $background;
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $transparent);
        imagealphablending($image, true);

        return $transparent;
    }

    private function paintModules(GdImage $image, QrCode $qrCode, QrGrid $grid, int $fg): void
    {
        $bs = $grid->moduleSize;

        foreach ($grid->dataModules() as [$row, $column]) {
            $x0 = (int) round($grid->x($column));
            $y0 = (int) round($grid->y($row));
            $x1 = (int) round($grid->x($column + 1)) - 1;
            $y1 = (int) round($grid->y($row + 1)) - 1;

            match ($qrCode->style) {
                'rounded' => GdShapes::roundedRect($image, $x0, $y0, $x1, $y1, (int) round($bs * 0.32), $fg),
                'dot' => imagefilledellipse($image, (int) round($grid->x($column + 0.5)), (int) round($grid->y($row + 0.5)), (int) round($bs * 0.92), (int) round($bs * 0.92), $fg),
                default => imagefilledrectangle($image, $x0, $y0, $x1, $y1, $fg),
            };
        }
    }

    private function paintEye(GdImage $image, QrCode $qrCode, float $x, float $y, float $bs, int $fg, int $hole): void
    {
        $style = (string) $qrCode->eye_style;

        GdShapes::eyeShape($image, $style, $x, $y, QrGrid::FINDER_SIZE * $bs, $fg);

        if ($qrCode->background_transparent) {
            imagealphablending($image, false);
        }

        GdShapes::eyeShape($image, $style, $x + $bs, $y + $bs, 5 * $bs, $hole);

        if ($qrCode->background_transparent) {
            imagealphablending($image, true);
        }

        GdShapes::eyeShape($image, $style, $x + 2 * $bs, $y + 2 * $bs, 3 * $bs, $fg);
    }

    private function paintLogo(GdImage $image, QrCode $qrCode, string $contents, int $size, float $bs): void
    {
        $logo = @imagecreatefromstring($contents);

        if (! $logo) {
            return;
        }

        imagesavealpha($logo, true);

        $box = (int) round($size * QrGrid::LOGO_RATIO);
        $pad = (int) round($box + $bs * 1.6);
        $padStart = intdiv($size - $pad, 2);
        GdShapes::roundedRect($image, $padStart, $padStart, $padStart + $pad - 1, $padStart + $pad - 1, (int) round($bs), GdShapes::color($image, $qrCode->background_color));

        $sourceW = imagesx($logo);
        $sourceH = imagesy($logo);
        $scale = min($box / $sourceW, $box / $sourceH);
        $targetW = max(1, (int) round($sourceW * $scale));
        $targetH = max(1, (int) round($sourceH * $scale));

        imagecopyresampled($image, $logo, intdiv($size - $targetW, 2), intdiv($size - $targetH, 2), 0, 0, $targetW, $targetH, $sourceW, $sourceH);
        imagedestroy($logo);
    }
}
