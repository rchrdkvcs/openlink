<?php

namespace App\Services\QrCodes;

use App\Models\QrCode as OpenlinkQrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Renders Openlink QR codes as SVG or PNG from the encoded module matrix,
 * applying the stored customization: module style, eye style, colors,
 * margin (in modules), optional transparent background, and centered logo.
 *
 * When a logo is present the error correction level is raised to at least
 * quartile so the modules hidden behind the logo stay recoverable.
 */
class QrCodeRenderer
{
    private const FINDER_SIZE = 7;

    private const LOGO_RATIO = 0.22;

    public function svg(OpenlinkQrCode $qrCode, string $url, ?int $size = null): string
    {
        $grid = $this->grid($qrCode, $url);
        $size = $size ?? (int) $qrCode->size;
        $modules = $grid['modules'];
        $count = $grid['count'];
        $total = $count + 2 * (int) $qrCode->margin;
        $bs = $total > 0 ? $size / $total : 1;
        $offset = (int) $qrCode->margin * $bs;

        $fg = $qrCode->foreground_color;
        $bg = $qrCode->background_color;

        $parts = [];
        $parts[] = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="geometricPrecision">';

        if (! $qrCode->background_transparent) {
            $parts[] = '<rect width="'.$size.'" height="'.$size.'" fill="'.$bg.'"/>';
        }

        $path = [];

        foreach ($modules as $row => $columns) {
            foreach ($columns as $column => $value) {
                if (! $value || $this->inFinder($row, $column, $count)) {
                    continue;
                }

                $x = $offset + $column * $bs;
                $y = $offset + $row * $bs;

                $path[] = match ($qrCode->style) {
                    'rounded' => $this->svgRoundedRect($x, $y, $bs, $bs, $bs * 0.32),
                    'dot' => $this->svgCircle($x + $bs / 2, $y + $bs / 2, $bs * 0.46),
                    default => $this->svgRect($x, $y, $bs, $bs),
                };
            }
        }

        $parts[] = '<path fill="'.$fg.'" d="'.implode(' ', $path).'"/>';

        foreach ($this->finderOrigins($count) as [$fRow, $fColumn]) {
            $parts[] = $this->svgEye($qrCode, $offset + $fColumn * $bs, $offset + $fRow * $bs, $bs, $fg);
        }

        if ($logo = $this->logoContents($qrCode)) {
            $parts[] = $this->svgLogo($qrCode, $logo, $size, $bs);
        }

        $parts[] = '</svg>';

        return implode('', $parts);
    }

    public function png(OpenlinkQrCode $qrCode, string $url, ?int $size = null): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $this->pngWithoutGd($qrCode, $url, $size);
        }

        $grid = $this->grid($qrCode, $url);
        $size = $size ?? (int) $qrCode->size;
        $modules = $grid['modules'];
        $count = $grid['count'];
        $total = $count + 2 * (int) $qrCode->margin;
        $bs = $total > 0 ? $size / $total : 1;
        $offset = (int) $qrCode->margin * $bs;

        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);

        $fg = $this->allocate($image, $qrCode->foreground_color);

        if ($qrCode->background_transparent) {
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagealphablending($image, false);
            imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $transparent);
            imagealphablending($image, true);
            $hole = $transparent;
        } else {
            $bg = $this->allocate($image, $qrCode->background_color);
            imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $bg);
            $hole = $bg;
        }

        foreach ($modules as $row => $columns) {
            foreach ($columns as $column => $value) {
                if (! $value || $this->inFinder($row, $column, $count)) {
                    continue;
                }

                $x0 = (int) round($offset + $column * $bs);
                $y0 = (int) round($offset + $row * $bs);
                $x1 = (int) round($offset + ($column + 1) * $bs) - 1;
                $y1 = (int) round($offset + ($row + 1) * $bs) - 1;

                match ($qrCode->style) {
                    'rounded' => $this->gdRoundedRect($image, $x0, $y0, $x1, $y1, (int) round($bs * 0.32), $fg),
                    'dot' => imagefilledellipse($image, (int) round($offset + ($column + 0.5) * $bs), (int) round($offset + ($row + 0.5) * $bs), (int) round($bs * 0.92), (int) round($bs * 0.92), $fg),
                    default => imagefilledrectangle($image, $x0, $y0, $x1, $y1, $fg),
                };
            }
        }

        foreach ($this->finderOrigins($count) as [$fRow, $fColumn]) {
            $this->gdEye($image, $qrCode, $offset + $fColumn * $bs, $offset + $fRow * $bs, $bs, $fg, $hole, $qrCode->background_transparent);
        }

        if ($logo = $this->logoContents($qrCode)) {
            $this->gdLogo($image, $qrCode, $logo, $size, $bs);
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @return array{modules: list<list<int>>, count: int} */
    private function grid(OpenlinkQrCode $qrCode, string $url): array
    {
        $matrix = Encoder::encode($url, $this->level($qrCode), 'UTF-8')->getMatrix();

        $modules = [];
        for ($row = 0; $row < $matrix->getHeight(); $row++) {
            for ($column = 0; $column < $matrix->getWidth(); $column++) {
                $modules[$row][$column] = $matrix->get($column, $row) === 1 ? 1 : 0;
            }
        }

        return ['modules' => $modules, 'count' => $matrix->getWidth()];
    }

    private function level(OpenlinkQrCode $qrCode): ErrorCorrectionLevel
    {
        $level = $qrCode->error_correction;

        if ($qrCode->hasLogo() && in_array($level, ['low', 'medium'], true)) {
            $level = 'quartile';
        }

        return match ($level) {
            'low' => ErrorCorrectionLevel::L(),
            'quartile' => ErrorCorrectionLevel::Q(),
            'high' => ErrorCorrectionLevel::H(),
            default => ErrorCorrectionLevel::M(),
        };
    }

    /** @return list<array{0: int, 1: int}> Finder pattern origins as [row, column]. */
    private function finderOrigins(int $count): array
    {
        return [[0, 0], [0, $count - self::FINDER_SIZE], [$count - self::FINDER_SIZE, 0]];
    }

    private function inFinder(int $row, int $column, int $count): bool
    {
        foreach ($this->finderOrigins($count) as [$fRow, $fColumn]) {
            if ($row >= $fRow && $row < $fRow + self::FINDER_SIZE && $column >= $fColumn && $column < $fColumn + self::FINDER_SIZE) {
                return true;
            }
        }

        return false;
    }

    private function logoContents(OpenlinkQrCode $qrCode): ?string
    {
        if (! $qrCode->hasLogo() || ! Storage::exists($qrCode->logo_path)) {
            return null;
        }

        return Storage::get($qrCode->logo_path);
    }

    // ── SVG shapes ───────────────────────────────────────────────────────────

    private function svgRect(float $x, float $y, float $w, float $h): string
    {
        return sprintf('M%s %sh%sv%sh%sz', $this->n($x), $this->n($y), $this->n($w), $this->n($h), $this->n(-$w));
    }

    private function svgRoundedRect(float $x, float $y, float $w, float $h, float $r): string
    {
        $r = min($r, $w / 2, $h / 2);

        return sprintf(
            'M%s %sh%sa%s %s 0 0 1 %s %sv%sa%s %s 0 0 1 -%s %sh-%sa%s %s 0 0 1 -%s -%sv-%sa%s %s 0 0 1 %s -%sz',
            $this->n($x + $r), $this->n($y),
            $this->n($w - 2 * $r),
            $this->n($r), $this->n($r), $this->n($r), $this->n($r),
            $this->n($h - 2 * $r),
            $this->n($r), $this->n($r), $this->n($r), $this->n($r),
            $this->n($w - 2 * $r),
            $this->n($r), $this->n($r), $this->n($r), $this->n($r),
            $this->n($h - 2 * $r),
            $this->n($r), $this->n($r), $this->n($r), $this->n($r),
        );
    }

    private function svgCircle(float $cx, float $cy, float $r): string
    {
        return sprintf(
            'M%s %sa%s %s 0 1 0 %s 0a%s %s 0 1 0 -%s 0z',
            $this->n($cx - $r), $this->n($cy),
            $this->n($r), $this->n($r), $this->n(2 * $r),
            $this->n($r), $this->n($r), $this->n(2 * $r),
        );
    }

    private function svgEye(OpenlinkQrCode $qrCode, float $x, float $y, float $bs, string $fg): string
    {
        $outer = self::FINDER_SIZE * $bs;
        $inner = 5 * $bs;
        $center = 3 * $bs;

        [$ringOuter, $ringInner, $pupil] = match ($qrCode->eye_style) {
            'rounded' => [
                $this->svgRoundedRect($x, $y, $outer, $outer, $bs * 2.1),
                $this->svgRoundedRect($x + $bs, $y + $bs, $inner, $inner, $bs * 1.5),
                $this->svgRoundedRect($x + 2 * $bs, $y + 2 * $bs, $center, $center, $bs * 0.9),
            ],
            'circle' => [
                $this->svgCircle($x + $outer / 2, $y + $outer / 2, $outer / 2),
                $this->svgCircle($x + $outer / 2, $y + $outer / 2, $inner / 2),
                $this->svgCircle($x + $outer / 2, $y + $outer / 2, $center / 2),
            ],
            default => [
                $this->svgRect($x, $y, $outer, $outer),
                $this->svgRect($x + $bs, $y + $bs, $inner, $inner),
                $this->svgRect($x + 2 * $bs, $y + 2 * $bs, $center, $center),
            ],
        };

        return '<path fill="'.$fg.'" fill-rule="evenodd" d="'.$ringOuter.' '.$ringInner.'"/>'
            .'<path fill="'.$fg.'" d="'.$pupil.'"/>';
    }

    private function svgLogo(OpenlinkQrCode $qrCode, string $contents, int $size, float $bs): string
    {
        $box = $size * self::LOGO_RATIO;
        $pad = $box + $bs * 1.6;
        $mime = $this->imageMime($contents);

        if (! $mime) {
            return '';
        }

        return '<rect x="'.$this->n(($size - $pad) / 2).'" y="'.$this->n(($size - $pad) / 2).'" width="'.$this->n($pad).'" height="'.$this->n($pad).'" rx="'.$this->n($bs).'" fill="'.$qrCode->background_color.'"/>'
            .'<image x="'.$this->n(($size - $box) / 2).'" y="'.$this->n(($size - $box) / 2).'" width="'.$this->n($box).'" height="'.$this->n($box).'" preserveAspectRatio="xMidYMid meet" href="data:'.$mime.';base64,'.base64_encode($contents).'"/>';
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    // ── GD shapes ────────────────────────────────────────────────────────────

    private function allocate(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    private function gdRoundedRect(GdImage $image, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
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

    private function gdEyeShape(GdImage $image, OpenlinkQrCode $qrCode, float $x, float $y, float $side, int $color): void
    {
        $x0 = (int) round($x);
        $y0 = (int) round($y);
        $x1 = (int) round($x + $side) - 1;
        $y1 = (int) round($y + $side) - 1;

        match ($qrCode->eye_style) {
            'rounded' => $this->gdRoundedRect($image, $x0, $y0, $x1, $y1, (int) round($side * 0.3), $color),
            'circle' => imagefilledellipse($image, (int) round($x + $side / 2), (int) round($y + $side / 2), (int) round($side), (int) round($side), $color),
            default => imagefilledrectangle($image, $x0, $y0, $x1, $y1, $color),
        };
    }

    private function gdEye(GdImage $image, OpenlinkQrCode $qrCode, float $x, float $y, float $bs, int $fg, int $hole, bool $transparent): void
    {
        $this->gdEyeShape($image, $qrCode, $x, $y, self::FINDER_SIZE * $bs, $fg);

        if ($transparent) {
            imagealphablending($image, false);
        }

        $this->gdEyeShape($image, $qrCode, $x + $bs, $y + $bs, 5 * $bs, $hole);

        if ($transparent) {
            imagealphablending($image, true);
        }

        $this->gdEyeShape($image, $qrCode, $x + 2 * $bs, $y + 2 * $bs, 3 * $bs, $fg);
    }

    private function gdLogo(GdImage $image, OpenlinkQrCode $qrCode, string $contents, int $size, float $bs): void
    {
        $logo = @imagecreatefromstring($contents);

        if (! $logo) {
            return;
        }

        imagesavealpha($logo, true);

        $box = (int) round($size * self::LOGO_RATIO);
        $pad = (int) round($box + $bs * 1.6);
        $padStart = intdiv($size - $pad, 2);
        $this->gdRoundedRect($image, $padStart, $padStart, $padStart + $pad - 1, $padStart + $pad - 1, (int) round($bs), $this->allocate($image, $qrCode->background_color));

        $sourceW = imagesx($logo);
        $sourceH = imagesy($logo);
        $scale = min($box / $sourceW, $box / $sourceH);
        $targetW = max(1, (int) round($sourceW * $scale));
        $targetH = max(1, (int) round($sourceH * $scale));

        imagecopyresampled(
            $image,
            $logo,
            intdiv($size - $targetW, 2),
            intdiv($size - $targetH, 2),
            0,
            0,
            $targetW,
            $targetH,
            $sourceW,
            $sourceH,
        );

        imagedestroy($logo);
    }

    private function imageMime(string $contents): ?string
    {
        $info = @getimagesizefromstring($contents);

        return $info['mime'] ?? null;
    }

    private function pngWithoutGd(OpenlinkQrCode $qrCode, string $url, ?int $size = null): string
    {
        $grid = $this->grid($qrCode, $url);
        $size = $size ?? (int) $qrCode->size;
        $modules = $grid['modules'];
        $count = $grid['count'];
        $total = $count + 2 * (int) $qrCode->margin;
        $scale = $total > 0 ? $size / $total : 1;
        $fg = $this->rgba($qrCode->foreground_color, 255);
        $bg = $this->rgba($qrCode->background_color, $qrCode->background_transparent ? 0 : 255);
        $raw = '';

        for ($y = 0; $y < $size; $y++) {
            $raw .= "\x00";

            for ($x = 0; $x < $size; $x++) {
                $column = (int) floor($x / $scale) - (int) $qrCode->margin;
                $row = (int) floor($y / $scale) - (int) $qrCode->margin;
                $on = $row >= 0
                    && $row < $count
                    && $column >= 0
                    && $column < $count
                    && ($modules[$row][$column] ?? 0) === 1;

                $raw .= $on ? $fg : $bg;
            }
        }

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNCCCCC', $size, $size, 8, 6, 0, 0, 0))
            .$this->pngChunk('IDAT', gzcompress($raw))
            .$this->pngChunk('IEND', '');
    }

    private function rgba(string $hex, int $alpha): string
    {
        $hex = ltrim($hex, '#');

        return chr((int) hexdec(substr($hex, 0, 2)))
            .chr((int) hexdec(substr($hex, 2, 2)))
            .chr((int) hexdec(substr($hex, 4, 2)))
            .chr($alpha);
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
