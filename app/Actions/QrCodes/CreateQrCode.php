<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateQrCode
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly QrCodeAppearance $appearance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, ShortLink $shortLink, array $data): QrCode
    {
        $this->access->requireEditableShortLink($request, $shortLink);

        return $shortLink->qrCodes()->create([
            'name' => $data['name'],
            'token' => Str::random(32),
            ...$this->appearance->defaults($data),
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('qr-logos') : null,
        ]);
    }
}
