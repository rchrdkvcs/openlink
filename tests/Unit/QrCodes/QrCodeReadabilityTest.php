<?php

namespace Tests\Unit\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\QrCodeLogoStorage;
use App\Services\QrCodes\Readability\QrCodeReadability;
use App\Services\QrCodes\Readability\QrDecoder;
use Mockery\MockInterface;
use Tests\TestCase;

class QrCodeReadabilityTest extends TestCase
{
    public function test_missing_decoder_reports_unavailable_instead_of_a_result(): void
    {
        $this->mock(QrDecoder::class, fn (MockInterface $mock) => $mock->shouldReceive('available')->andReturnFalse());

        $result = app(QrCodeReadability::class)->check($this->qrCode(), 256);

        $this->assertSame(['status' => 'unavailable', 'decoded' => null], $result['formats']['png']);
        $this->assertSame(['status' => 'unavailable', 'decoded' => null, 'derived_from' => 'png'], $result['formats']['svg']);
    }

    public function test_svg_with_a_logo_the_png_renderer_cannot_read_reports_unsupported(): void
    {
        $this->mock(QrCodeLogoStorage::class, fn (MockInterface $mock) => $mock->shouldReceive('contents')->andReturn('not an image'));

        $result = app(QrCodeReadability::class)->check($this->qrCode(['logo_path' => 'qr-logos/logo.svg']), 256);

        $this->assertSame('readable', $result['formats']['png']['status']);
        $this->assertSame('unsupported', $result['formats']['svg']['status']);
        $this->assertNull($result['formats']['svg']['decoded']);
    }

    public function test_decoded_content_that_differs_reports_a_mismatch_for_both_formats(): void
    {
        $this->mock(QrDecoder::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturnTrue();
            $mock->shouldReceive('decode')->once()->andReturn('Hello');
        });

        $result = app(QrCodeReadability::class)->check($this->qrCode(), 256);

        $this->assertSame(['status' => 'mismatch', 'decoded' => 'Hello'], $result['formats']['png']);
        $this->assertSame(['status' => 'mismatch', 'decoded' => 'Hello', 'derived_from' => 'png'], $result['formats']['svg']);
    }

    public function test_png_is_rendered_at_the_bounded_decode_size(): void
    {
        $this->mock(QrDecoder::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturnTrue();
            $mock->shouldReceive('decode')->once()->withArgs(fn ($image) => imagesx($image) === QrCodeReadability::MAX_DECODE_PIXELS)->andReturn('Hello world');
        });

        $result = app(QrCodeReadability::class)->check($this->qrCode(), 4096);

        $this->assertSame('readable', $result['formats']['png']['status']);
        $this->assertSame(4096, $result['summary']['size']);
    }

    public function test_decoder_failures_are_reported_as_unreadable(): void
    {
        $this->assertNull(app(QrDecoder::class)->decode(imagecreatetruecolor(1, 1)));
    }

    private function qrCode(array $attributes = []): QrCode
    {
        return new QrCode([
            ...$attributes,
            'payload_type' => 'text',
            'content' => 'Hello world',
            'size' => 256,
            'foreground_color' => '#111827',
            'background_color' => '#ffffff',
            'margin' => 2,
            'error_correction' => 'medium',
            'style' => 'square',
            'eye_style' => 'square',
            'background_transparent' => false,
        ]);
    }
}
