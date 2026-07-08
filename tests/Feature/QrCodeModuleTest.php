<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\QrCodeRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class QrCodeModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_codes_have_a_module_page_for_direct_payloads(): void
    {
        [$workspace, $user] = $this->workspace();

        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Lobby Wi-Fi',
            'token' => 'module-token',
            'payload_type' => 'wifi',
            'payload' => ['ssid' => 'Lobby', 'encryption' => 'WPA', 'password' => 'secret', 'hidden' => false],
            'content' => 'WIFI:T:WPA;S:Lobby;P:secret;H:false;;',
        ]);

        $this->actingAs($user)
            ->get(route('qr-codes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QrCodes/Index')
                ->where('qrCodes.0.token', $qrCode->token)
                ->where('payloadTypes.wifi', 'Wi-Fi'));
    }

    public function test_qr_code_with_direct_payload_can_be_created_and_exported(): void
    {
        [, $user] = $this->workspace();

        $this->actingAs($user)
            ->post(route('qr-codes.store-direct'), [
                'name' => 'Support email',
                'payload_type' => 'email',
                'payload' => [
                    'email' => 'support@example.com',
                    'subject' => 'Help',
                    'body' => 'Hello',
                ],
                'style' => 'dot',
                'eye_style' => 'circle',
            ])
            ->assertRedirect();

        $qrCode = QrCode::query()->where('name', 'Support email')->firstOrFail();

        $this->assertNull($qrCode->short_link_id);
        $this->assertSame('mailto:support@example.com?subject=Help&body=Hello', $qrCode->content);
        $this->assertSame('dot', $qrCode->style);
        $this->assertSame('circle', $qrCode->eye_style);

        $this->actingAs($user)
            ->get(route('qr-codes.export', [$qrCode, 'svg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }

    public function test_direct_qr_code_exports_encode_the_native_payload(): void
    {
        [$workspace, $user] = $this->workspace();
        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Lobby Wi-Fi',
            'token' => 'native-wifi-token',
            'payload_type' => 'wifi',
            'payload' => ['ssid' => 'Lobby', 'encryption' => 'WPA', 'password' => 'secret', 'hidden' => false],
            'content' => 'WIFI:T:WPA;S:Lobby;P:secret;H:false;;',
        ]);

        $this->mock(QrCodeRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('svg')
                ->once()
                ->with(Mockery::type(QrCode::class), 'WIFI:T:WPA;S:Lobby;P:secret;H:false;;', null)
                ->andReturn('<svg />');
        });

        $this->actingAs($user)
            ->get(route('qr-codes.export', [$qrCode, 'svg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_qr_code_content_can_change_without_changing_the_public_url(): void
    {
        [$workspace, $user] = $this->workspace();
        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Campaign',
            'token' => 'stable-module-token',
            'payload_type' => 'url',
            'payload' => ['url' => 'https://example.com/old'],
            'content' => 'https://example.com/old',
        ]);

        $publicUrl = $qrCode->publicUrl();

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), [
                'payload_type' => 'url',
                'payload' => ['url' => 'https://example.com/new'],
            ])
            ->assertRedirect();

        $qrCode->refresh();

        $this->assertSame('stable-module-token', $qrCode->token);
        $this->assertSame($publicUrl, $qrCode->publicUrl());
        $this->assertSame('https://example.com/new', $qrCode->content);
    }

    public function test_public_qr_redirects_to_the_current_url_payload(): void
    {
        [$workspace] = $this->workspace();
        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Campaign',
            'token' => 'redirect-token',
            'payload_type' => 'url',
            'payload' => ['url' => 'https://example.com/current'],
            'content' => 'https://example.com/current',
        ]);

        $this->get('/qr/'.$qrCode->token)
            ->assertRedirect('https://example.com/current');
    }

    public function test_public_qr_displays_non_redirect_payloads(): void
    {
        [$workspace] = $this->workspace();
        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Lobby Wi-Fi',
            'token' => 'wifi-token',
            'payload_type' => 'wifi',
            'payload' => ['ssid' => 'Lobby', 'encryption' => 'WPA', 'password' => 'secret', 'hidden' => false],
            'content' => 'WIFI:T:WPA;S:Lobby;P:secret;H:false;;',
        ]);

        $this->get('/qr/'.$qrCode->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/QrCodePayload')
                ->where('payloadType', 'wifi')
                ->where('content', 'WIFI:T:WPA;S:Lobby;P:secret;H:false;;'));
    }

    public function test_members_of_other_workspaces_cannot_manage_qr_codes(): void
    {
        [$workspace] = $this->workspace();
        [, $outsider] = $this->workspace('Other');
        $qrCode = $workspace->qrCodes()->create([
            'name' => 'Private',
            'token' => 'private-token',
            'payload_type' => 'text',
            'payload' => ['text' => 'Secret'],
            'content' => 'Secret',
        ]);

        $this->actingAs($outsider)->get(route('qr-codes.show', $qrCode))->assertForbidden();
        $this->actingAs($outsider)->patch(route('qr-codes.update', $qrCode), ['name' => 'Hijack'])->assertForbidden();
        $this->actingAs($outsider)->delete(route('qr-codes.destroy', $qrCode))->assertForbidden();
    }

    /** @return array{Workspace, User} */
    private function workspace(string $name = 'Events'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        return [$workspace, $user];
    }
}
