<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;

class QrCodePayload
{
    public static function make(QrCode $qrCode): array
    {
        $shortLink = $qrCode->shortLink;

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
            'short_link_id' => $qrCode->short_link_id,
            'short_link' => $shortLink ? [
                'id' => $shortLink->id,
                'slug' => $shortLink->slug,
                'short_url' => $shortLink->shortUrl(),
                'destination_url' => $shortLink->destination_url,
            ] : null,
            'scans' => $qrCode->scanCount(),
            'created_at' => $qrCode->created_at,
            'updated_at' => $qrCode->updated_at,
        ];
    }
}
