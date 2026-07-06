<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeleteQrCode
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, QrCode $qrCode): void
    {
        $this->access->requireEditableQrCode($request, $qrCode);

        if ($qrCode->hasLogo()) {
            Storage::delete($qrCode->logo_path);
        }

        $qrCode->delete();
    }
}
