<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateDirectQrCode
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly QrCodeContent $content,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, array $data): QrCode
    {
        $workspace = $this->access->requireEditableWorkspace($request);
        $type = (string) $data['payload_type'];
        $payload = $data['payload'] ?? [];

        return $workspace->qrCodes()->create([
            'name' => $data['name'],
            'token' => Str::random(32),
            'payload_type' => $type,
            'payload' => $payload,
            'content' => $this->content->normalize($type, $payload),
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
