<?php

namespace App\Services\QrCodes\Readability;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeRenderer;
use App\Services\QrCodes\Rendering\QrGrid;
use App\Services\ShortLinks\ShortLinkLifecycle;
use GdImage;

class QrCodeReadability
{
    public const MAX_DECODE_PIXELS = 1024;

    public function __construct(
        private readonly QrCodeRenderer $renderer,
        private readonly SvgRasterizer $rasterizer,
        private readonly QrDecoder $decoder,
        private readonly ReadabilityWarnings $warnings,
        private readonly ShortLinkLifecycle $lifecycle,
    ) {}

    public function check(QrCode $qrCode, int $size): array
    {
        $expected = $qrCode->encodedContent();
        $grid = QrGrid::for($qrCode, $expected, $size);

        return [
            'size' => $size,
            'formats' => [
                'png' => $this->decodeFormat($expected, fn () => $this->pngRaster($qrCode, $expected, $size)),
                'svg' => $this->decodeFormat($expected, fn () => $this->rasterizer->rasterize($this->renderer->svg($qrCode, $expected, $size), $this->decodeSize($size))),
            ],
            'warnings' => $this->warnings->for($qrCode, $grid),
            'summary' => $this->summary($qrCode, $grid, $expected),
        ];
    }

    private function decodeFormat(string $expected, callable $raster): array
    {
        if (! $this->decoder->available()) {
            return ['status' => 'unavailable', 'decoded' => null];
        }

        try {
            $decoded = $this->decoder->decode($raster());
        } catch (UnsupportedImage $exception) {
            return ['status' => 'unsupported', 'decoded' => null, 'reason' => $exception->getMessage()];
        }

        return [
            'status' => match (true) {
                $decoded === null => 'unreadable',
                $decoded === $expected => 'readable',
                default => 'mismatch',
            },
            'decoded' => $decoded,
        ];
    }

    private function pngRaster(QrCode $qrCode, string $expected, int $size): GdImage
    {
        $source = @imagecreatefromstring($this->renderer->png($qrCode, $expected, $size));

        if (! $source) {
            throw new UnsupportedImage('The PNG export could not be read.');
        }

        $target = $this->decodeSize($size);
        $image = imagecreatetruecolor($target, $target);
        imagefilledrectangle($image, 0, 0, $target - 1, $target - 1, imagecolorallocate($image, 255, 255, 255));
        imagealphablending($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $target, $target, imagesx($source), imagesy($source));

        return $image;
    }

    private function decodeSize(int $size): int
    {
        return min($size, self::MAX_DECODE_PIXELS);
    }

    private function summary(QrCode $qrCode, QrGrid $grid, string $expected): array
    {
        $shortLink = $qrCode->hasDirectPayload() ? null : $qrCode->shortLink;
        $shortLink?->loadMissing('domain');

        return [
            'encoded_content' => $expected,
            'is_direct' => $qrCode->hasDirectPayload(),
            'payload_type' => $qrCode->hasDirectPayload() ? $qrCode->payload_type : null,
            'size' => $grid->size,
            'modules' => $grid->count,
            'quiet_zone' => $grid->margin,
            'module_pixels' => round($grid->moduleSize, 2),
            'error_correction' => QrGrid::errorCorrection($qrCode),
            'contrast' => $qrCode->background_transparent ? null : ReadabilityWarnings::contrast($qrCode->foreground_color, $qrCode->background_color),
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
