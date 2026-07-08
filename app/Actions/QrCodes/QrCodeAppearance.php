<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;

class QrCodeAppearance
{
    /** @return array<string, mixed> */
    public function defaults(array $data = []): array
    {
        return [
            'size' => $data['size'] ?? 1024,
            'foreground_color' => $data['foreground_color'] ?? '#111827',
            'background_color' => $data['background_color'] ?? '#ffffff',
            'margin' => $data['margin'] ?? 2,
            'error_correction' => $data['error_correction'] ?? 'medium',
            'style' => $data['style'] ?? 'square',
            'eye_style' => $data['eye_style'] ?? 'square',
            'background_transparent' => (bool) ($data['background_transparent'] ?? false),
        ];
    }

    /** @param array<string, mixed> $data */
    public function fill(QrCode $qrCode, array $data): void
    {
        $qrCode->fill(collect($data)->only([
            'name',
            'size',
            'foreground_color',
            'background_color',
            'margin',
            'error_correction',
            'style',
            'eye_style',
        ])->filter(fn ($value) => $value !== null)->all());

        if (array_key_exists('background_transparent', $data) && $data['background_transparent'] !== null) {
            $qrCode->background_transparent = (bool) $data['background_transparent'];
        }
    }

    /** @param array<string, mixed> $data */
    public function previewOverrides(array $data): array
    {
        return collect($data)
            ->except(['name', 'payload_type', 'payload', 'logo', 'remove_logo'])
            ->filter(fn ($value) => $value !== null)
            ->all();
    }
}
