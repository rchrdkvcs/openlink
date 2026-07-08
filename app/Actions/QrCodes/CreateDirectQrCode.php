<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateDirectQrCode
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly QrCodeContent $content,
        private readonly QrCodeAppearance $appearance,
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
            ...$this->appearance->defaults($data),
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('qr-logos') : null,
        ]);
    }
}
