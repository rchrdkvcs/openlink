<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateQrCode
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly QrCodeContent $content,
        private readonly QrCodeAppearance $appearance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, QrCode $qrCode, array $data): QrCode
    {
        $this->access->requireEditableQrCode($request, $qrCode);

        if ($qrCode->hasDirectPayload() && (array_key_exists('payload_type', $data) || array_key_exists('payload', $data))) {
            $type = (string) ($data['payload_type'] ?? $qrCode->payload_type);
            $payload = $data['payload'] ?? $qrCode->payload;

            $qrCode->payload_type = $type;
            $qrCode->payload = $payload;
            $qrCode->content = $this->content->normalize($type, $payload);
        }

        $this->appearance->fill($qrCode, $data);

        if ($request->hasFile('logo')) {
            $this->deleteLogo($qrCode);
            $qrCode->logo_path = $request->file('logo')->store('qr-logos');
        } elseif ($data['remove_logo'] ?? false) {
            $this->deleteLogo($qrCode);
            $qrCode->logo_path = null;
        }

        $qrCode->save();

        return $qrCode;
    }

    private function deleteLogo(QrCode $qrCode): void
    {
        if ($qrCode->hasLogo()) {
            Storage::delete($qrCode->logo_path);
        }
    }
}
