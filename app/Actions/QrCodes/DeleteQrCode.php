<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteQrCode
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, QrCode $qrCode): void
    {
        $this->access->requireEditableQrCode($request, $qrCode);

        $logoPath = $qrCode->logo_path;

        if (! $qrCode->delete()) {
            throw new RuntimeException('Unable to delete the QR Code.');
        }

        if ($logoPath) {
            Storage::delete($logoPath);
        }
    }
}
