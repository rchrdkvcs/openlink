<?php

namespace App\Services\QrCodes\Rendering;

use App\Models\QrCode;

class SvgQrRenderer
{
    public function render(QrCode $qrCode, QrGrid $grid, ?string $logo): string
    {
        $size = $grid->size;
        $fg = $qrCode->foreground_color;
        $parts = ['<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="geometricPrecision">'];

        if (! $qrCode->background_transparent) {
            $parts[] = '<rect width="'.$size.'" height="'.$size.'" fill="'.$qrCode->background_color.'"/>';
        }

        $parts[] = '<path fill="'.$fg.'" d="'.implode(' ', $this->modulePaths($qrCode, $grid)).'"/>';

        foreach ($grid->finderPositions() as [$x, $y]) {
            $parts[] = $this->eye($qrCode, $x, $y, $grid->moduleSize, $fg);
        }

        if ($logo !== null) {
            $parts[] = $this->logo($qrCode, $logo, $size, $grid->moduleSize);
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    private function modulePaths(QrCode $qrCode, QrGrid $grid): array
    {
        $bs = $grid->moduleSize;
        $paths = [];

        foreach ($grid->dataModules() as [$row, $column]) {
            $x = $grid->x($column);
            $y = $grid->y($row);

            $paths[] = match ($qrCode->style) {
                'rounded' => SvgShapes::roundedRect($x, $y, $bs, $bs, $bs * 0.32),
                'dot' => SvgShapes::circle($x + $bs / 2, $y + $bs / 2, $bs * 0.46),
                default => SvgShapes::rect($x, $y, $bs, $bs),
            };
        }

        return $paths;
    }

    private function eye(QrCode $qrCode, float $x, float $y, float $bs, string $fg): string
    {
        $outer = QrGrid::FINDER_SIZE * $bs;
        $inner = 5 * $bs;
        $center = 3 * $bs;
        $mid = $outer / 2;

        [$ringOuter, $ringInner, $pupil] = match ($qrCode->eye_style) {
            'rounded' => [
                SvgShapes::roundedRect($x, $y, $outer, $outer, $bs * 2.1),
                SvgShapes::roundedRect($x + $bs, $y + $bs, $inner, $inner, $bs * 1.5),
                SvgShapes::roundedRect($x + 2 * $bs, $y + 2 * $bs, $center, $center, $bs * 0.9),
            ],
            'circle' => [
                SvgShapes::circle($x + $mid, $y + $mid, $outer / 2),
                SvgShapes::circle($x + $mid, $y + $mid, $inner / 2),
                SvgShapes::circle($x + $mid, $y + $mid, $center / 2),
            ],
            default => [
                SvgShapes::rect($x, $y, $outer, $outer),
                SvgShapes::rect($x + $bs, $y + $bs, $inner, $inner),
                SvgShapes::rect($x + 2 * $bs, $y + 2 * $bs, $center, $center),
            ],
        };

        return '<path fill="'.$fg.'" fill-rule="evenodd" d="'.$ringOuter.' '.$ringInner.'"/>'
            .'<path fill="'.$fg.'" d="'.$pupil.'"/>';
    }

    private function logo(QrCode $qrCode, string $contents, int $size, float $bs): string
    {
        $info = @getimagesizefromstring($contents);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;

        if (! $mime) {
            return '';
        }

        $box = $size * QrGrid::LOGO_RATIO;
        $pad = $box + $bs * 1.6;
        $padStart = SvgShapes::n(($size - $pad) / 2);
        $boxStart = SvgShapes::n(($size - $box) / 2);

        return '<rect x="'.$padStart.'" y="'.$padStart.'" width="'.SvgShapes::n($pad).'" height="'.SvgShapes::n($pad).'" rx="'.SvgShapes::n($bs).'" fill="'.$qrCode->background_color.'"/>'
            .'<image x="'.$boxStart.'" y="'.$boxStart.'" width="'.SvgShapes::n($box).'" height="'.SvgShapes::n($box).'" preserveAspectRatio="xMidYMid meet" href="data:'.$mime.';base64,'.base64_encode($contents).'"/>';
    }
}
