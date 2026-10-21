<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeLogoStorage;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateQrCode
{
    public function __construct(
        private readonly Gate $gate,
        private readonly QrCodeTargets $targets,
        private readonly QrCodeAppearance $appearance,
        private readonly QrCodeLogoStorage $logos,
    ) {}

    public function handle(Request $request, QrCode $qrCode, array $data): QrCode
    {
        $this->gate->forUser($request->user())->authorize('update', $qrCode);

        if (QrCodeTargets::submitted($data)) {
            $qrCode->fill($this->targets->resolve($qrCode->workspace, $data, $qrCode)->attributes());
        }

        $this->appearance->fill($qrCode, $data);

        if (! $request->hasFile('logo') && isset($data['logo_template_id'])) {
            return $this->logos->saveCopy($qrCode, $this->templateLogo($qrCode, (int) $data['logo_template_id']));
        }

        return $this->logos->save(
            $qrCode,
            $request->hasFile('logo') ? $request->file('logo') : null,
            (bool) ($data['remove_logo'] ?? false),
        );
    }

    private function templateLogo(QrCode $qrCode, int $templateId): string
    {
        $path = QrCodeTemplates::logoOf($qrCode, $templateId);

        if (! $this->logos->exists($path)) {
            throw ValidationException::withMessages(['logo_template_id' => 'This template’s logo is no longer available.']);
        }

        return $path;
    }
}
