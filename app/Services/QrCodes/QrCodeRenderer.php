<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\Rendering\GdQrRenderer;
use App\Services\QrCodes\Rendering\PlainPngRenderer;
use App\Services\QrCodes\Rendering\QrGrid;
use App\Services\QrCodes\Rendering\SvgQrRenderer;

class QrCodeRenderer
{
    public function __construct(
        private readonly QrCodeLogoStorage $logos,
        private readonly SvgQrRenderer $svg,
        private readonly GdQrRenderer $gd,
        private readonly PlainPngRenderer $plainPng,
    ) {}

    public function svg(QrCode $qrCode, string $content, ?int $size = null): string
    {
        return $this->svg->render($qrCode, QrGrid::for($qrCode, $content, $size), $this->logos->contents($qrCode));
    }

    public function png(QrCode $qrCode, string $content, ?int $size = null): string
    {
        $grid = QrGrid::for($qrCode, $content, $size);

        if (! function_exists('imagecreatetruecolor')) {
            return $this->plainPng->render($qrCode, $grid);
        }

        return $this->gd->render($qrCode, $grid, $this->logos->contents($qrCode));
    }
}
