<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateQrCode
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
        $shortLink = null;

        if (filled($data['short_link_id'] ?? null)) {
            $shortLink = ShortLink::query()->find($data['short_link_id']);

            if (! $shortLink || $shortLink->workspace_id !== $workspace->id) {
                throw ValidationException::withMessages(['short_link_id' => 'The selected Short Link is not available in this Workspace.']);
            }
        }

        if (! $shortLink && (! filled($data['payload_type'] ?? null) || ! array_key_exists('payload', $data))) {
            throw ValidationException::withMessages(['payload_type' => 'Choose a Short Link or a direct payload type.']);
        }

        if ($shortLink && (filled($data['payload_type'] ?? null) || array_key_exists('payload', $data))) {
            throw ValidationException::withMessages(['short_link_id' => 'Choose either a Short Link or a direct payload, not both.']);
        }

        $type = $shortLink ? null : (string) $data['payload_type'];
        $payload = $shortLink ? null : $data['payload'];

        return $workspace->qrCodes()->create([
            'name' => $data['name'],
            'token' => Str::random(32),
            'short_link_id' => $shortLink?->id,
            'payload_type' => $type,
            'payload' => $payload,
            'content' => $type ? $this->content->normalize($type, $payload) : null,
            ...$this->appearance->defaults($data),
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('qr-logos') : null,
        ]);
    }
}
