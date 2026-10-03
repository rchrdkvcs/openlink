<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;

class QrCodeAppearance
{
    private const FIELDS = ['size', 'foreground_color', 'background_color', 'margin', 'error_correction', 'style', 'eye_style', 'background_transparent'];

    public static function rules(): array
    {
        return [
            'size' => ['nullable', 'integer', 'min:128', 'max:4096'],
            'foreground_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'margin' => ['nullable', 'integer', 'min:0', 'max:16'],
            'error_correction' => ['nullable', 'in:'.implode(',', QrCode::ERROR_CORRECTIONS)],
            'style' => ['nullable', 'in:'.implode(',', QrCode::STYLES)],
            'eye_style' => ['nullable', 'in:'.implode(',', QrCode::EYE_STYLES)],
            'background_transparent' => ['nullable', 'boolean'],
        ];
    }

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

    public function fill(QrCode $qrCode, array $data): void
    {
        $qrCode->fill($this->overrides($data, ['name']));
    }

    public function preview(QrCode $qrCode, array $data): void
    {
        $qrCode->fill($this->overrides($data));
    }

    private function overrides(array $data, array $extra = []): array
    {
        $values = collect($data)
            ->only([...$extra, ...self::FIELDS])
            ->filter(fn ($value) => $value !== null)
            ->all();

        if (array_key_exists('background_transparent', $values)) {
            $values['background_transparent'] = (bool) $values['background_transparent'];
        }

        return $values;
    }
}
