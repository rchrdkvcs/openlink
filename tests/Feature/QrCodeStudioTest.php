<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrCodeStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_url_lives_on_the_link_domain(): void
    {
        [, , , $qrCode] = $this->linkWithQrCode();

        $this->assertSame('https://go.example.test/qr/'.$qrCode->token, $qrCode->publicUrl());
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

    public function test_store_accepts_customization_and_redirects_to_the_studio(): void
    {
        [, , $user, , $link] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->post(route('qr-codes.store', $link), [
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
                ->has('link.short_url'));
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
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
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

    public function test_destroy_deletes_the_qr_code_and_its_logo(): void
    {
        Storage::fake();
        [, , $user, $qrCode] = $this->linkWithQrCode();
        $logoPath = UploadedFile::fake()->image('logo.png')->store('qr-logos');
        $qrCode->update(['logo_path' => $logoPath]);

        $this->actingAs($user)
            ->delete(route('qr-codes.destroy', $qrCode))
            ->assertRedirect(route('links.index'));

        $this->assertDatabaseMissing('qr_codes', ['id' => $qrCode->id]);
        Storage::assertMissing($logoPath);
    }

    public function test_members_of_other_workspaces_cannot_touch_the_qr_code(): void
    {
        [, , , $qrCode] = $this->linkWithQrCode();

        $outsider = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $outsider->id, 'name' => 'Other', 'slug' => 'other', 'settings' => []]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $outsider->id, 'role' => WorkspaceMember::ROLE_OWNER]);

        $this->actingAs($outsider)->get(route('qr-codes.show', $qrCode))->assertForbidden();
        $this->actingAs($outsider)->patch(route('qr-codes.update', $qrCode), ['name' => 'Hijack'])->assertForbidden();
        $this->actingAs($outsider)->delete(route('qr-codes.destroy', $qrCode))->assertForbidden();
    }

    public function test_scan_through_the_qr_entry_redirects_to_the_destination(): void
    {
        [, , , $qrCode] = $this->linkWithQrCode();

        $this->get('/qr/'.$qrCode->token)
            ->assertRedirect('https://example.com/destination');
    }

    public function test_api_update_and_destroy_mirror_the_web_flow(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/qr-codes/'.$qrCode->token, ['style' => 'dot'])
            ->assertOk()
            ->assertJsonPath('data.style', 'dot')
            ->assertJsonPath('data.public_url', 'https://go.example.test/qr/'.$qrCode->token);

        $this->withToken($token)
            ->deleteJson('/api/v1/qr-codes/'.$qrCode->token)
            ->assertOk();

        $this->assertDatabaseMissing('qr_codes', ['id' => $qrCode->id]);
    }

    /** @return array{Workspace, Domain, User, QrCode, ShortLink} */
    private function linkWithQrCode(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Events',
            'slug' => 'events',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'go.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'promo',
            'destination_url' => 'https://example.com/destination',
        ]);
        $qrCode = $link->qrCodes()->create([
            'name' => 'Poster',
            'token' => 'studio-qr-token',
        ]);

        return [$workspace, $domain, $user, $qrCode->fresh(), $link];
    }
}
