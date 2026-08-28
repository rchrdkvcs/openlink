<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        $workspace = $this->access->requireEditableQrCode($request, $qrCode);

        $targetWasSubmitted = array_key_exists('short_link_id', $data)
            || array_key_exists('payload_type', $data)
            || array_key_exists('payload', $data);

        if ($targetWasSubmitted) {
            $shortLinkId = $data['short_link_id'] ?? null;

            if (filled($shortLinkId)) {
                if (filled($data['payload_type'] ?? null) || filled($data['payload'] ?? null)) {
                    throw ValidationException::withMessages(['short_link_id' => 'Choose either a Short Link or a direct payload, not both.']);
                }

                $shortLink = ShortLink::query()->find($shortLinkId);
                if (! $shortLink || $shortLink->workspace_id !== $workspace->id) {
                    throw ValidationException::withMessages(['short_link_id' => 'The selected Short Link is not available in this Workspace.']);
                }

                $qrCode->short_link_id = $shortLink->id;
                $qrCode->payload_type = null;
                $qrCode->payload = null;
                $qrCode->content = null;
            } else {
                $type = (string) ($data['payload_type'] ?? ($qrCode->hasDirectPayload() ? $qrCode->payload_type : ''));
                $payload = $data['payload'] ?? ($qrCode->hasDirectPayload() ? $qrCode->payload : null);
                if (! filled($type) || ! is_array($payload)) {
                    throw ValidationException::withMessages(['payload_type' => 'Choose a direct payload type and provide its content.']);
                }

                $qrCode->short_link_id = null;
                $qrCode->payload_type = $type;
                $qrCode->payload = $payload;
                $qrCode->content = $this->content->normalize($type, $payload);
            }
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
