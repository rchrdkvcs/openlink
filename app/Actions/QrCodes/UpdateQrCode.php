<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeLogoStorage;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

class UpdateQrCode
{
    public function __construct(
        private readonly Gate $gate,
        private readonly QrCodeTargets $targets,
        private readonly QrCodeAppearance $appearance,
        private readonly QrCodeTemplates $templates,
        private readonly QrCodeLogoStorage $logos,
    ) {}

    public function handle(Request $request, QrCode $qrCode, array $data): QrCode
    {
        $this->gate->forUser($request->user())->authorize('update', $qrCode);

        if (QrCodeTargets::submitted($data)) {
            $qrCode->fill($this->targets->resolve($qrCode->workspace, $data, $qrCode)->attributes());
        }

        $template = isset($data['template_id']) ? $this->templates->find($qrCode, (int) $data['template_id']) : null;

        if ($template) {
            $this->templates->apply($qrCode, $template);
        }

        $this->appearance->fill($qrCode, $data);

        $upload = $request->hasFile('logo') ? $request->file('logo') : null;
        $remove = (bool) ($data['remove_logo'] ?? false);

        if ($template && ! $upload && ! $remove) {
            return $this->logos->saveCopy($qrCode, $this->templates->logoOf($template, 'template_id'));
        }

        return $this->logos->save($qrCode, $upload, $remove);
    }
}
