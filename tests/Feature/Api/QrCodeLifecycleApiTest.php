<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesQrCodes;
use Tests\TestCase;

class QrCodeLifecycleApiTest extends TestCase
{
    use CreatesQrCodes;
    use RefreshDatabase;

    public function test_qr_code_can_be_created_and_previewed_via_api(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $link = $this->shortLink($workspace, $domain, 'qr-target', [
            'destination_url' => 'https://example.com/qr',
            'is_enabled' => true,
        ]);

        $create = $this->postJson('/api/v1/qr-codes', ['name' => 'Poster', 'short_link_id' => $link->id]);
        $create->assertCreated()->assertJsonStructure(['data' => ['id', 'name', 'token', 'public_url']]);

        $token = $create->json('data.token');

        $this->get('/api/v1/qr-codes/'.$token.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->get('/api/v1/qr-codes/'.$token.'/export/svg')
            ->assertOk()
            ->assertDownload('poster.svg');
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
}
