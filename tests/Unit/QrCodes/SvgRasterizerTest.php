<?php

namespace Tests\Unit\QrCodes;

use App\Services\QrCodes\Readability\SvgRasterizer;
use App\Services\QrCodes\Readability\UnsupportedImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SvgRasterizerTest extends TestCase
{
    public static function unsupportedDocuments(): array
    {
        return [
            'malformed xml' => ['<svg'],
            'non svg root' => ['<html/>'],
            'offset viewBox' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="1 0 10 10"/>'],
            'unknown element' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" fill="#000000"/></svg>'],
            'transform attribute' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h5v5h-5z" fill="#000000" transform="scale(2)"/></svg>'],
            'named fill' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h5v5h-5z" fill="black"/></svg>'],
            'curve command' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0C1 1 2 2 3 3z" fill="#000000"/></svg>'],
            'remote image' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><image x="0" y="0" width="10" height="10" href="https://example.com/logo.png"/></svg>'],
        ];
    }

    #[DataProvider('unsupportedDocuments')]
    public function test_it_rejects_svg_outside_the_rendered_subset(string $svg): void
    {
        $this->expectException(UnsupportedImage::class);

        (new SvgRasterizer)->rasterize($svg, 20);
    }

    public function test_it_fills_even_odd_rings_with_a_hole_and_scales_to_the_target_size(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 10 10">'
            .'<path fill="#000000" fill-rule="evenodd" d="M0 0h10v10h-10z M3 3h4v4h-4z"/></svg>';

        $image = (new SvgRasterizer)->rasterize($svg, 100);

        $this->assertSame(100, imagesx($image));
        $this->assertSame(0x000000, imagecolorat($image, 10, 10));
        $this->assertSame(0xFFFFFF, imagecolorat($image, 50, 50));
    }

    public function test_it_flattens_arcs_and_paints_rounded_rects(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            .'<rect width="10" height="10" rx="5" fill="#ff0000"/>'
            .'<path fill="#0000ff" d="M3 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0z"/></svg>';

        $image = (new SvgRasterizer)->rasterize($svg, 100);

        $this->assertSame(0xFFFFFF, imagecolorat($image, 1, 1));
        $this->assertSame(0xFF0000, imagecolorat($image, 50, 15));
        $this->assertSame(0x0000FF, imagecolorat($image, 50, 50));
    }
}
