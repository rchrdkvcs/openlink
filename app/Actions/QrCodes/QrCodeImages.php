<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeRenderer;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QrCodeImages
{
    public const FORMATS = ['png', 'svg'];

    public function __construct(
        private readonly QrCodeRenderer $renderer,
        private readonly QrCodeAppearance $appearance,
        private readonly QrCodeTargets $targets,
    ) {}

    public function export(QrCode $qrCode, string $format, ?int $size = null): Response
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);

        $content = $qrCode->encodedContent();
        $image = $format === 'png'
            ? $this->renderer->png($qrCode, $content, $size)
            : $this->renderer->svg($qrCode, $content, $size);
        $filename = (Str::slug($qrCode->name) ?: $qrCode->token).'.'.$format;

        return response($image, 200, [
            'Content-Type' => $format === 'png' ? 'image/png' : 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function preview(QrCode $qrCode, array $overrides): Response
    {
        $this->appearance->preview($qrCode, $overrides);

        if ($qrCode->hasDirectPayload() && QrCodeTargets::submitted($overrides)) {
            $direct = collect($overrides)->only(['payload_type', 'payload'])->all();
            $qrCode->fill($this->targets->resolve($qrCode->workspace, $direct, $qrCode)->attributes());
        }

        return response($this->renderer->svg($qrCode, $qrCode->encodedContent()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$qrCode->token.'.svg"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
