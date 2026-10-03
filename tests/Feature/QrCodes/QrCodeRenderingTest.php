<?php

namespace Tests\Feature\QrCodes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesQrCodes;
use Tests\TestCase;

class QrCodeRenderingTest extends TestCase
{
    use CreatesQrCodes;
    use RefreshDatabase;

    public function test_public_url_lives_on_the_link_domain(): void
    {
        [, , , $qrCode] = $this->linkWithQrCode();

        $this->assertSame('https://go.example.test/qr/'.$qrCode->token, $qrCode->publicUrl());
    }

    public function test_qr_code_export_returns_svg(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'poster', ['destination_url' => 'https://example.com/poster']);
        $qrCode = $link->qrCodes()->create([
            'name' => 'Poster',
            'token' => 'qr-token',
        ]);

        $this->actingAs($user)
            ->get(route('qr-codes.export', [$qrCode, 'svg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_png_export_downloads_a_png_image(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $response = $this->actingAs($user)
            ->get(route('qr-codes.export', [$qrCode, 'png']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_png_export_accepts_a_size_override(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $contents = $this->actingAs($user)
            ->get(route('qr-codes.export', [$qrCode, 'png']).'?size=256')
            ->assertOk()
            ->getContent();

        [$width, $height] = getimagesizefromstring($contents);
        $this->assertSame([256, 256], [$width, $height]);
    }

    public function test_qr_code_preview_returns_inline_svg(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        $link = $this->shortLink($workspace, $domain, 'badge', ['destination_url' => 'https://example.com/badge']);
        $qrCode = $link->qrCodes()->create([
            'name' => 'Badge',
            'token' => 'badge-qr-token',
        ]);

        $this->actingInWorkspace($user, $workspace)
            ->get(route('qr-codes.preview', $qrCode))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'inline; filename="'.$qrCode->token.'.svg"');
    }

    public function test_preview_applies_unsaved_query_overrides(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $svg = $this->actingAs($user)
            ->get(route('qr-codes.preview', $qrCode).'?foreground_color=%23FF0000&style=dot&size=256')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->getContent();

        $this->assertStringContainsString('#FF0000', $svg);
        $this->assertStringNotContainsString($qrCode->foreground_color, $svg);
        $this->assertSame('square', $qrCode->fresh()->style, 'Preview overrides must not be persisted.');
    }
}
