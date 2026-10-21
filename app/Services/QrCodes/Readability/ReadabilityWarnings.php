<?php

namespace App\Services\QrCodes\Readability;

use App\Models\QrCode;
use App\Services\QrCodes\Rendering\QrGrid;

class ReadabilityWarnings
{
    public const MIN_CONTRAST = 4.5;

    public const POOR_CONTRAST = 3.0;

    public const MIN_MODULE_PIXELS = 4;

    public const LOGO_BUDGET = 0.6;

    private const RECOVERY = ['low' => 0.07, 'medium' => 0.15, 'quartile' => 0.25, 'high' => 0.30];

    public function for(QrCode $qrCode, QrGrid $grid): array
    {
        return array_values(array_filter([
            ...$this->colors($qrCode),
            $this->quietZone($grid),
            $this->moduleSize($grid),
            $this->logo($qrCode, $grid),
        ]));
    }

    public static function contrast(string $foreground, string $background): float
    {
        $light = max(self::luminance($foreground), self::luminance($background));
        $dark = min(self::luminance($foreground), self::luminance($background));

        return round(($light + 0.05) / ($dark + 0.05), 2);
    }

    public static function logoCoverage(QrGrid $grid): float
    {
        $covered = min($grid->logoModules(), $grid->count);

        return round(($covered ** 2) / ($grid->count ** 2), 3);
    }

    public static function recovery(string $level): float
    {
        return self::RECOVERY[$level] ?? self::RECOVERY['medium'];
    }

    private function colors(QrCode $qrCode): array
    {
        if ($qrCode->background_transparent) {
            return [$this->warning(
                'transparent_background',
                'notice',
                'Contrast depends on the printing surface',
                'The background is transparent, so contrast comes from whatever the code is printed on. The check decodes it over white; print it on a light, plain surface.',
            )];
        }

        $warnings = [];
        $ratio = self::contrast($qrCode->foreground_color, $qrCode->background_color);

        if ($ratio < self::MIN_CONTRAST) {
            $warnings[] = $this->warning(
                'low_contrast',
                $ratio < self::POOR_CONTRAST ? 'warning' : 'notice',
                'Weak contrast',
                'The modules and background have a '.$ratio.':1 contrast ratio. Use at least '.self::MIN_CONTRAST.':1, ideally a dark color on white.',
            );
        }

        if (self::luminance($qrCode->foreground_color) > self::luminance($qrCode->background_color)) {
            $warnings[] = $this->warning(
                'inverted_colors',
                'warning',
                'Light modules on a dark background',
                'Some scanners only read dark modules on a light background. Swap the colors for the widest support.',
            );
        }

        return $warnings;
    }

    private function quietZone(QrGrid $grid): ?array
    {
        return match ($grid->margin) {
            0 => $this->warning('quiet_zone', 'warning', 'No quiet zone', 'Scanners need an empty border around the code. Set the margin to at least 2 modules, or 4 when printing next to other artwork.'),
            1 => $this->warning('quiet_zone', 'notice', 'Narrow quiet zone', 'A 1-module margin can fail next to busy artwork. Use at least 2 modules, or 4 for print.'),
            default => null,
        };
    }

    private function moduleSize(QrGrid $grid): ?array
    {
        if ($grid->moduleSize >= self::MIN_MODULE_PIXELS) {
            return null;
        }

        return $this->warning(
            'small_modules',
            'warning',
            'Modules are too small',
            'Each module is '.round($grid->moduleSize, 2).' px at '.$grid->size.' px. Export at a larger size so each module is at least '.self::MIN_MODULE_PIXELS.' px.',
        );
    }

    private function logo(QrCode $qrCode, QrGrid $grid): ?array
    {
        if (! $qrCode->hasLogo()) {
            return null;
        }

        $level = QrGrid::errorCorrection($qrCode);
        $coverage = self::logoCoverage($grid);

        if ($coverage <= self::recovery($level) * self::LOGO_BUDGET) {
            return null;
        }

        return $this->warning(
            'logo_obstruction',
            'warning',
            'Logo hides too much of the code',
            'The logo covers about '.round($coverage * 100).'% of the modules, close to or beyond what '.$level.' error correction can recover. Use high error correction or a smaller margin.',
        );
    }

    private function warning(string $code, string $severity, string $title, string $message): array
    {
        return compact('code', 'severity', 'title', 'message');
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
