<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateQrCode
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, QrCode $qrCode, array $data): QrCode
    {
        $this->access->requireEditableQrCode($request, $qrCode);

        $qrCode->fill(collect($data)->only([
            'name',
            'size',
            'foreground_color',
            'background_color',
            'margin',
            'error_correction',
            'style',
            'eye_style',
        ])->filter(fn ($value) => $value !== null)->all());

        if (array_key_exists('background_transparent', $data) && $data['background_transparent'] !== null) {
            $qrCode->background_transparent = (bool) $data['background_transparent'];
        }

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
