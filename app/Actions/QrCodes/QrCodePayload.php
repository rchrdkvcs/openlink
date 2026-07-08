<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;

class QrCodePayload
{
    /**
     * Validation rules shared by the web and API QR code endpoints.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $creating = true): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'size' => ['nullable', 'integer', 'min:128', 'max:4096'],
            'foreground_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'margin' => ['nullable', 'integer', 'min:0', 'max:16'],
            'error_correction' => ['nullable', 'in:'.implode(',', QrCode::ERROR_CORRECTIONS)],
            'style' => ['nullable', 'in:'.implode(',', QrCode::STYLES)],
            'eye_style' => ['nullable', 'in:'.implode(',', QrCode::EYE_STYLES)],
            'background_transparent' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function directRules(bool $creating = true): array
    {
        return [
            ...self::rules($creating),
            'payload_type' => [$creating ? 'required' : 'sometimes', 'string', 'in:'.implode(',', QrCode::PAYLOAD_TYPES)],
            'payload' => [$creating ? 'required' : 'sometimes', 'array'],
        ];
    }

    /** @return array<string, mixed> */
    public static function make(QrCode $qrCode): array
    {
        return [
            ...$qrCode->only([
                'id',
                'name',
                'token',
                'payload_type',
                'payload',
                'content',
                'size',
                'foreground_color',
                'background_color',
                'margin',
                'error_correction',
                'style',
                'eye_style',
                'background_transparent',
            ]),
            'has_logo' => $qrCode->hasLogo(),
            'public_url' => $qrCode->publicUrl(),
            'is_direct' => $qrCode->hasDirectPayload(),
            'scans' => $qrCode->hasDirectPayload() ? 0 : (int) ($qrCode->scans_count ?? $qrCode->analyticsEvents()->successful()->where('metric', 'scan')->count()),
            'created_at' => $qrCode->created_at,
            'updated_at' => $qrCode->updated_at,
        ];
    }
}
