<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateQrCode
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, ShortLink $shortLink, array $data): QrCode
    {
        $this->access->requireEditableShortLink($request, $shortLink);

        return $shortLink->qrCodes()->create([
            'name' => $data['name'],
            'token' => Str::random(32),
            'size' => $data['size'] ?? 1024,
            'foreground_color' => $data['foreground_color'] ?? '#111827',
            'background_color' => $data['background_color'] ?? '#ffffff',
            'margin' => $data['margin'] ?? 2,
            'error_correction' => $data['error_correction'] ?? 'medium',
            'style' => $data['style'] ?? 'square',
            'eye_style' => $data['eye_style'] ?? 'square',
            'background_transparent' => (bool) ($data['background_transparent'] ?? false),
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('qr-logos') : null,
        ]);
    }
}
