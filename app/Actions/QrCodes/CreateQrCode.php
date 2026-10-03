<?php

namespace App\Actions\QrCodes;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\QrCode;
use App\Services\QrCodes\QrCodeLogoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateQrCode
{
    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly QrCodeTargets $targets,
        private readonly QrCodeAppearance $appearance,
        private readonly QrCodeLogoStorage $logos,
    ) {}

    public function handle(Request $request, array $data): QrCode
    {
        $workspace = $this->current->require('editContent');

        $qrCode = $workspace->qrCodes()->make([
            'name' => $data['name'],
            'token' => Str::random(32),
            ...$this->targets->resolve($workspace, $data)->attributes(),
            ...$this->appearance->defaults($data),
        ]);

        return $this->logos->save($qrCode, $request->hasFile('logo') ? $request->file('logo') : null);
    }
}
