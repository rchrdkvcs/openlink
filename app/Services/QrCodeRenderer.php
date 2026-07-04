<?php

namespace App\Services;

use App\Models\QrCode as OpenlinkQrCode;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeRenderer
{
    public function png(OpenlinkQrCode $qrCode, string $url): string
    {
        return (new Builder(
            writer: new PngWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $this->level($qrCode->error_correction),
            size: $qrCode->size,
            margin: $qrCode->margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $this->color($qrCode->foreground_color),
            backgroundColor: $this->color($qrCode->background_color),
        ))
            ->build()
            ->getString();
    }

    public function svg(OpenlinkQrCode $qrCode, string $url): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $this->level($qrCode->error_correction),
            size: $qrCode->size,
            margin: $qrCode->margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $this->color($qrCode->foreground_color),
            backgroundColor: $this->color($qrCode->background_color),
        ))
            ->build()
            ->getString();
    }

    private function level(string $level): ErrorCorrectionLevel
    {
        return match ($level) {
            'low' => ErrorCorrectionLevel::Low,
            'quartile' => ErrorCorrectionLevel::Quartile,
            'high' => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::Medium,
        };
    }

    private function color(string $hex): Color
    {
        $hex = ltrim($hex, '#');

        return new Color(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
}
