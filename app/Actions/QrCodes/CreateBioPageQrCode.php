<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\BioPage;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateBioPageQrCode
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly QrCodeAppearance $appearance,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Request $request, BioPage $bioPage, array $data): QrCode
    {
        $workspace = $this->access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id, 403);
        Gate::authorize('update', $bioPage);

        return $bioPage->qrCodes()->create([
            'workspace_id' => $bioPage->workspace_id,
            'name' => $data['name'],
            'token' => Str::random(32),
            ...$this->appearance->defaults($data),
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('qr-logos') : null,
        ]);
    }
}
