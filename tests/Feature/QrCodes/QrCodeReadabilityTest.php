<?php

namespace Tests\Feature\QrCodes;

use App\Models\QrCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesQrCodes;
use Tests\TestCase;

class QrCodeReadabilityTest extends TestCase
{
    use CreatesQrCodes;
    use RefreshDatabase;

    public function test_linked_qr_code_decodes_to_its_stable_entry_url_in_both_formats(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $response = $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'style' => 'dot', 'eye_style' => 'circle']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $entryUrl = 'https://go.example.test/qr/'.$qrCode->token;
        $response
            ->assertJsonPath('formats.png.status', 'readable')
            ->assertJsonPath('formats.png.decoded', $entryUrl)
            ->assertJsonPath('formats.svg.status', 'readable')
            ->assertJsonPath('formats.svg.decoded', $entryUrl)
            ->assertJsonPath('warnings', [])
            ->assertJsonPath('summary.encoded_content', $entryUrl)
            ->assertJsonPath('summary.is_direct', false)
            ->assertJsonPath('summary.size', 512)
            ->assertJsonPath('summary.short_link.short_url', 'https://go.example.test/promo')
            ->assertJsonPath('summary.short_link.status', 'active')
            ->assertJsonPath('summary.short_link.domain_active', true);
    }

    public function test_direct_unicode_payload_decodes_exactly_in_both_formats(): void
    {
        [$workspace, , $user] = $this->linkWithQrCode();
        $text = "Café ünïcødé 漢字 😀\nSecond line";
        $qrCode = $this->directQrCode($workspace, 'text', ['text' => $text]);

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512]))
            ->assertOk()
            ->assertJsonPath('formats.png.status', 'readable')
            ->assertJsonPath('formats.png.decoded', $text)
            ->assertJsonPath('formats.svg.status', 'readable')
            ->assertJsonPath('formats.svg.decoded', $text)
            ->assertJsonPath('summary.is_direct', true)
            ->assertJsonPath('summary.payload_type', 'text')
            ->assertJsonPath('summary.short_link', null);
    }

    public function test_unsaved_native_payload_is_checked_without_being_saved(): void
    {
        [$workspace, , $user] = $this->linkWithQrCode();
        $qrCode = $this->directQrCode($workspace, 'text', ['text' => 'Saved']);
        $wifi = ['ssid' => 'Café Guest', 'encryption' => 'WPA', 'password' => 'p;a:ss"word'];
        $expected = app(QrCodeContent::class)->normalize('wifi', $wifi);

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'payload_type' => 'wifi', 'payload' => $wifi]))
            ->assertOk()
            ->assertJsonPath('summary.encoded_content', $expected)
            ->assertJsonPath('formats.png.decoded', $expected)
            ->assertJsonPath('formats.svg.decoded', $expected);

        $this->assertSame('Saved', $qrCode->fresh()->content);
    }

    public function test_low_contrast_is_flagged_and_identical_colors_cannot_be_decoded(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'foreground_color' => '#d1d5db', 'background_color' => '#ffffff']))
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'low_contrast')
            ->assertJsonPath('warnings.0.severity', 'warning')
            ->assertJsonPath('summary.contrast', 1.47);

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'foreground_color' => '#ffffff', 'background_color' => '#ffffff']))
            ->assertOk()
            ->assertJsonPath('formats.png.status', 'unreadable')
            ->assertJsonPath('formats.png.decoded', null)
            ->assertJsonPath('formats.svg.status', 'unreadable');
    }

    public function test_light_modules_on_a_dark_background_are_flagged(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $codes = $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'foreground_color' => '#ffffff', 'background_color' => '#111827']))
            ->assertOk()
            ->json('warnings.*.code');

        $this->assertSame(['inverted_colors'], $codes);
    }

    public function test_cropped_quiet_zone_is_flagged(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'margin' => 0]))
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'quiet_zone')
            ->assertJsonPath('warnings.0.severity', 'warning')
            ->assertJsonPath('summary.quiet_zone', 0);

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'margin' => 1]))
            ->assertOk()
            ->assertJsonPath('warnings.0.severity', 'notice');
    }

    public function test_small_export_size_flags_small_modules(): void
    {
        [$workspace, , $user] = $this->linkWithQrCode();
        $qrCode = $this->directQrCode($workspace, 'text', ['text' => str_repeat('Long native payload ', 20)]);

        $response = $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 128]))
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'small_modules');

        $this->assertLessThan(4, $response->json('summary.module_pixels'));
    }

    public function test_transparent_background_reports_that_contrast_depends_on_the_printing_surface(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'background_transparent' => 1]))
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'transparent_background')
            ->assertJsonPath('warnings.0.severity', 'notice')
            ->assertJsonPath('summary.contrast', null)
            ->assertJsonPath('formats.png.status', 'readable')
            ->assertJsonPath('formats.svg.status', 'readable');
    }

    public function test_logo_obstruction_is_flagged_and_validated_against_the_styled_output(): void
    {
        Storage::fake();
        [, , $user, $qrCode] = $this->linkWithQrCode();
        Storage::put('qr-logos/logo.png', $this->solidLogo());
        $qrCode->update(['logo_path' => 'qr-logos/logo.png']);

        $readable = $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512]))
            ->assertOk();
        $readable->assertJsonPath('warnings', [])
            ->assertJsonPath('summary.error_correction', 'quartile')
            ->assertJsonPath('formats.png.status', 'readable')
            ->assertJsonPath('formats.svg.status', 'readable');

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512, 'margin' => 16]))
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'logo_obstruction')
            ->assertJsonPath('formats.png.status', 'unreadable')
            ->assertJsonPath('formats.svg.status', 'unreadable');
    }

    public function test_lifecycle_restrictions_are_summarised_for_linked_qr_codes(): void
    {
        [, , $user, $qrCode, $link] = $this->linkWithQrCode();
        $link->update([
            'expires_at' => now()->subDay(),
            'visit_limit' => 10,
            'successful_visits' => 3,
            'password_hash' => bcrypt('secret'),
        ]);

        $this->actingAs($user)
            ->getJson($this->checkUrl($qrCode, ['size' => 512]))
            ->assertOk()
            ->assertJsonPath('summary.short_link.status', 'expired')
            ->assertJsonPath('summary.short_link.visit_limit', 10)
            ->assertJsonPath('summary.short_link.successful_visits', 3)
            ->assertJsonPath('summary.short_link.has_password', true)
            ->assertJsonPath('summary.short_link.expires_at', $link->fresh()->expires_at->toIso8601String());
    }

    public function test_checks_make_no_visits_and_record_no_analytics(): void
    {
        [, , $user, $qrCode, $link] = $this->linkWithQrCode();
        $link->update(['visit_limit' => 1, 'successful_visits' => 0]);

        $this->actingAs($user)->getJson($this->checkUrl($qrCode, ['size' => 512]))->assertOk();

        $this->assertSame(0, $link->fresh()->successful_visits);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_viewers_can_check_but_outsiders_and_guests_cannot(): void
    {
        [$workspace, , , $qrCode] = $this->linkWithQrCode();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingInWorkspace($viewer, $workspace)
            ->getJson($this->checkUrl($qrCode, ['size' => 256]))
            ->assertOk();

        $outsider = User::factory()->create();
        $this->workspaceFor($outsider, 'Other', 'other', WorkspaceMember::ROLE_OWNER);
        $this->actingAs($outsider)->getJson($this->checkUrl($qrCode, ['size' => 256]))->assertForbidden();

        auth()->logout();
        $this->get($this->checkUrl($qrCode, ['size' => 256]))->assertRedirect(route('login'));
    }

    public function test_invalid_export_settings_and_payloads_are_rejected(): void
    {
        [$workspace, , $user, $qrCode] = $this->linkWithQrCode();
        $direct = $this->directQrCode($workspace, 'text', ['text' => 'Saved']);

        $this->actingAs($user)->getJson($this->checkUrl($qrCode, ['size' => 64]))->assertUnprocessable()->assertJsonValidationErrors('size');
        $this->actingAs($user)->getJson($this->checkUrl($qrCode, ['size' => 9000]))->assertUnprocessable();
        $this->actingAs($user)->getJson($this->checkUrl($qrCode, ['foreground_color' => 'red']))->assertUnprocessable();
        $this->actingAs($user)
            ->getJson($this->checkUrl($direct, ['payload_type' => 'url', 'payload' => ['url' => 'not a url']]))
            ->assertUnprocessable();
    }

    private function checkUrl(QrCode $qrCode, array $query): string
    {
        return route('qr-codes.readability', $qrCode).'?'.http_build_query($query);
    }

    private function directQrCode(Workspace $workspace, string $type, array $payload): QrCode
    {
        return QrCode::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Native',
            'token' => 'native-'.$type.'-'.str()->random(6),
            'payload_type' => $type,
            'payload' => $payload,
            'content' => app(QrCodeContent::class)->normalize($type, $payload),
        ])->fresh();
    }

    private function solidLogo(): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefilledrectangle($image, 0, 0, 63, 63, imagecolorallocate($image, 17, 24, 39));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
