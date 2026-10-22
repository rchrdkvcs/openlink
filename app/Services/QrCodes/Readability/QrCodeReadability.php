<?php

namespace App\Services\QrCodes\Readability;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeLogoStorage;
use App\Services\QrCodes\QrCodeRenderer;
use App\Services\QrCodes\Rendering\QrGrid;
use App\Services\ShortLinks\ShortLinkLifecycle;
use GdImage;

class QrCodeReadability
{
    public const MAX_DECODE_PIXELS = 1024;

    public function __construct(
        private readonly QrCodeRenderer $renderer,
        private readonly QrCodeLogoStorage $logos,
        private readonly QrDecoder $decoder,
        private readonly ReadabilityWarnings $warnings,
        private readonly ShortLinkLifecycle $lifecycle,
    ) {}

    public function check(QrCode $qrCode, int $size, ?float $printWidthMm = null): array
    {
        $expected = $qrCode->encodedContent();
        $grid = QrGrid::for($qrCode, $expected, $size);
        $png = $this->decodePng($qrCode, $expected, min($size, self::MAX_DECODE_PIXELS));

        return [
            'size' => $size,
            'formats' => [
                'png' => $png,
                'svg' => $this->deriveSvg($qrCode, $png),
            ],
            'warnings' => $this->warnings->for($qrCode, $grid, $printWidthMm),
            'summary' => $this->summary($qrCode, $grid, $expected, $printWidthMm),
        ];
    }

    private function decodePng(QrCode $qrCode, string $expected, int $size): array
    {
        if (! $this->decoder->available()) {
            return ['status' => 'unavailable', 'decoded' => null];
        }

        $raster = $this->onWhite($this->renderer->png($qrCode, $expected, $size));

        if ($raster === null) {
            return ['status' => 'unsupported', 'decoded' => null, 'reason' => 'The PNG export could not be read.'];
        }

        $decoded = $this->decoder->decode($raster);

        return [
            'status' => match (true) {
                $decoded === null => 'unreadable',
                $decoded === $expected => 'readable',
                default => 'mismatch',
            },
            'decoded' => $decoded,
        ];
    }

    private function deriveSvg(QrCode $qrCode, array $png): array
    {
        $logo = $this->logos->contents($qrCode);

        if ($png['status'] !== 'unavailable' && $logo !== null && ! @imagecreatefromstring($logo)) {
            return [
                'status' => 'unsupported',
                'decoded' => null,
                'reason' => 'The PNG renderer cannot read this logo, so the SVG cannot be checked through it.',
            ];
        }

        return [...$png, 'derived_from' => 'png'];
    }

    private function onWhite(string $png): ?GdImage
    {
        $source = @imagecreatefromstring($png);

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 255, 255, 255));
        imagealphablending($image, true);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);

        return $image;
    }

    private function summary(QrCode $qrCode, QrGrid $grid, string $expected, ?float $printWidthMm): array
    {
        $shortLink = $qrCode->hasDirectPayload() ? null : $qrCode->shortLink;
        $shortLink?->loadMissing('domain');
        $totalModules = $grid->count + 2 * $grid->margin;
        $contrast = $qrCode->background_transparent ? null : ReadabilityWarnings::contrast($qrCode->foreground_color, $qrCode->background_color);

        return [
            'encoded_content' => $expected,
            'is_direct' => $qrCode->hasDirectPayload(),
            'payload_type' => $qrCode->hasDirectPayload() ? $qrCode->payload_type : null,
            'size' => $grid->size,
            'modules' => $grid->count,
            'quiet_zone' => $grid->margin,
            'module_pixels' => round($grid->moduleSize, 2),
            'min_module_pixels' => ReadabilityWarnings::MIN_MODULE_PIXELS,
            'print_width_mm' => $printWidthMm,
            'module_mm' => $printWidthMm === null ? null : round($printWidthMm / $totalModules, 2),
            'min_module_mm' => ReadabilityWarnings::MIN_MODULE_MM,
            'min_print_width_mm' => (int) ceil($totalModules * ReadabilityWarnings::MIN_MODULE_MM),
            'error_correction' => QrGrid::errorCorrection($qrCode),
            'contrast' => $contrast === null ? null : round($contrast, 2),
            'short_link' => $shortLink ? [
                'short_url' => $shortLink->shortUrl(),
                'status' => $this->lifecycle->status($shortLink),
                'domain' => $shortLink->domain->hostname,
                'domain_active' => $shortLink->domain->isUsable(),
                'activates_at' => $shortLink->activates_at?->toIso8601String(),
                'expires_at' => $shortLink->expires_at?->toIso8601String(),
                'visit_limit' => $shortLink->visit_limit,
                'successful_visits' => (int) $shortLink->successful_visits,
                'has_password' => $shortLink->hasPassword(),
            ] : null,
        ];
    }
}
