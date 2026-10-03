<?php

namespace Tests\Feature\QrCodes;

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesQrCodes;
use Tests\TestCase;

class QrCodeStudioTest extends TestCase
{
    use CreatesQrCodes;
    use RefreshDatabase;

    public function test_store_accepts_customization_and_redirects_to_the_studio(): void
    {
        [, , $user, , $link] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->post(route('qr-codes.store'), [
                'short_link_id' => $link->id,
                'name' => 'Booth banner',
                'style' => 'dot',
                'eye_style' => 'circle',
                'background_transparent' => true,
            ])
            ->assertRedirect();

        $qrCode = $link->qrCodes()->where('name', 'Booth banner')->firstOrFail();
        $this->assertSame('dot', $qrCode->style);
        $this->assertSame('circle', $qrCode->eye_style);
        $this->assertTrue($qrCode->background_transparent);
    }

    public function test_studio_page_renders_with_qr_payload(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->get(route('qr-codes.show', $qrCode))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QrCodes/Show')
                ->where('qr.token', $qrCode->token)
                ->where('qr.public_url', 'https://go.example.test/qr/'.$qrCode->token)
                ->where('qr.short_link.short_url', 'https://go.example.test/promo')
                ->has('shortLinks'));
    }

    public function test_update_changes_settings_and_manages_the_logo(): void
    {
        Storage::fake();
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), [
                'name' => 'Booth',
                'style' => 'rounded',
                'eye_style' => 'rounded',
                'foreground_color' => '#123456',
                'logo' => $this->logoUpload(),
            ])
            ->assertRedirect();

        $qrCode->refresh();
        $this->assertSame('Booth', $qrCode->name);
        $this->assertSame('rounded', $qrCode->style);
        $this->assertSame('#123456', $qrCode->foreground_color);
        $this->assertTrue($qrCode->hasLogo());
        Storage::assertExists($qrCode->logo_path);

        $logoPath = $qrCode->logo_path;

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), ['remove_logo' => true])
            ->assertRedirect();

        $this->assertFalse($qrCode->refresh()->hasLogo());
        Storage::assertMissing($logoPath);
    }

    public function test_destroy_deletes_the_qr_code_and_its_logo(): void
    {
        Storage::fake();
        [, , $user, $qrCode] = $this->linkWithQrCode();
        $logoPath = $this->logoUpload()->store('qr-logos');
        $qrCode->update(['logo_path' => $logoPath]);

        $this->actingAs($user)
            ->delete(route('qr-codes.destroy', $qrCode))
            ->assertRedirect(route('qr-codes.index'));

        $this->assertDatabaseMissing('qr_codes', ['id' => $qrCode->id]);
        Storage::assertMissing($logoPath);
    }

    public function test_members_of_other_workspaces_cannot_touch_the_qr_code(): void
    {
        [, , , $qrCode] = $this->linkWithQrCode();

        $outsider = User::factory()->create();
        $this->workspaceFor($outsider, 'Other', 'other', WorkspaceMember::ROLE_OWNER);

        $this->actingAs($outsider)->get(route('qr-codes.show', $qrCode))->assertForbidden();
        $this->actingAs($outsider)->patch(route('qr-codes.update', $qrCode), ['name' => 'Hijack'])->assertForbidden();
        $this->actingAs($outsider)->delete(route('qr-codes.destroy', $qrCode))->assertForbidden();
    }

    private function logoUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
        );
    }
}
