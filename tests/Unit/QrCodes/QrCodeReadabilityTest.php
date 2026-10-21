<?php

namespace Tests\Unit\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\Readability\QrCodeReadability;
use App\Services\QrCodes\Readability\QrDecoder;
use App\Services\QrCodes\Readability\SvgRasterizer;
use App\Services\QrCodes\Readability\UnsupportedImage;
use Mockery\MockInterface;
use Tests\TestCase;

class QrCodeReadabilityTest extends TestCase
{
    public function test_missing_decoder_reports_unavailable_instead_of_a_result(): void
    {
        $this->mock(QrDecoder::class, fn (MockInterface $mock) => $mock->shouldReceive('available')->andReturnFalse());

        $result = app(QrCodeReadability::class)->check($this->qrCode(), 256);

        $this->assertSame(['status' => 'unavailable', 'decoded' => null], $result['formats']['png']);
        $this->assertSame(['status' => 'unavailable', 'decoded' => null], $result['formats']['svg']);
    }

    public function test_svg_outside_the_supported_subset_reports_unsupported(): void
    {
        $this->mock(SvgRasterizer::class, fn (MockInterface $mock) => $mock->shouldReceive('rasterize')->andThrow(new UnsupportedImage('Unsupported SVG element <filter>.')));

        $result = app(QrCodeReadability::class)->check($this->qrCode(), 256);

        $this->assertSame('readable', $result['formats']['png']['status']);
        $this->assertSame('unsupported', $result['formats']['svg']['status']);
        $this->assertSame('Unsupported SVG element <filter>.', $result['formats']['svg']['reason']);
    }

    public function test_decoded_content_that_differs_reports_a_mismatch(): void
    {
        $this->mock(QrDecoder::class, function (MockInterface $mock) {
            $mock->shouldReceive('available')->andReturnTrue();
            $mock->shouldReceive('decode')->andReturn('Hello', 'Hello world');
        });

        $result = app(QrCodeReadability::class)->check($this->qrCode(), 256);

        $this->assertSame(['status' => 'mismatch', 'decoded' => 'Hello'], $result['formats']['png']);
        $this->assertSame(['status' => 'readable', 'decoded' => 'Hello world'], $result['formats']['svg']);
    }

    private function qrCode(): QrCode
    {
        return new QrCode([
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
