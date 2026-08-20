<?php

namespace App\Services\BioPages;

class BioTheme
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'appearance' => 'dark',
            'backgroundType' => 'color',
            'backgroundColor' => '#17171c',
            'gradientColor' => '#4f46e5',
            'textColor' => '#f7f7f8',
            'destinationColor' => '#ffffff',
            'destinationTextColor' => '#17171c',
            'destinationStyle' => 'solid',
            'destinationRadius' => 'large',
            'destinationShadow' => true,
            'profileShape' => 'circle',
            'fontFamily' => 'sans',
            'imageFit' => 'cover',
            'overlayOpacity' => 30,
        ];
    }

    /** @param array<string, mixed> $theme @return array<string, mixed> */
    public function withDefaults(array $theme): array
    {
        return array_merge($this->defaults(), $theme);
    }

    public function contrastRatio(string $foreground, string $background): float
    {
        $first = $this->luminance($foreground);
        $second = $this->luminance($background);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    private function luminance(string $color): float
    {
        $channels = [
            hexdec(substr($color, 1, 2)),
            hexdec(substr($color, 3, 2)),
            hexdec(substr($color, 5, 2)),
        ];
        $channels = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
