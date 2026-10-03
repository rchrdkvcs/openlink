<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\QrCodes\QrCodeRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class QrCodeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_payload_preview_renders_unsaved_payload_overrides(): void
    {
        [$workspace, $user] = $this->workspace();
        Sanctum::actingAs($user);
        $qrCode = $this->directQrCode($workspace);

        $this->expectPreviewOf('Updated text');

        $this->get('/api/v1/qr-codes/'.$qrCode->token.'/preview?'.http_build_query([
            'payload_type' => 'text',
            'payload' => ['text' => 'Updated text'],
            'style' => 'dot',
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertSame('Original text', $qrCode->fresh()->content);
    }

    public function test_web_and_api_previews_share_direct_payload_validation(): void
    {
        [$workspace, $user] = $this->workspace();
        $qrCode = $this->directQrCode($workspace);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/qr-codes/'.$qrCode->token.'/preview?payload_type=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload_type');

        $this->getJson('/api/v1/qr-codes/'.$qrCode->token.'/preview?payload_type=url&payload[url]=not-a-url')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload.url');

        $this->actingAs($user)
            ->get(route('qr-codes.preview', $qrCode).'?payload_type=bogus')
            ->assertSessionHasErrors('payload_type');
    }

    public function test_preview_ignores_target_overrides_for_short_link_qr_codes(): void
    {
        [$workspace, $user] = $this->workspace();
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'go.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'token-'.str()->random(8),
            'verified_at' => now(),
        ]);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'poster',
            'destination_url' => 'https://example.com',
            'is_enabled' => true,
        ]);
        $qrCode = $link->qrCodes()->create(['name' => 'Poster', 'token' => 'linked-preview-token']);
        Sanctum::actingAs($user);

        $this->expectPreviewOf('https://go.example.test/qr/linked-preview-token');

        $this->get('/api/v1/qr-codes/'.$qrCode->token.'/preview?short_link_id=999&payload_type=text&payload[text]=x')
            ->assertOk();
    }

    public function test_create_requires_a_complete_target(): void
    {
        [, $user] = $this->workspace();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/qr-codes', ['name' => 'Empty', 'payload_type' => 'text', 'payload' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload_type');

        $this->postJson('/api/v1/qr-codes', ['name' => 'Both', 'short_link_id' => 1, 'payload_type' => 'text', 'payload' => ['text' => 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('short_link_id');
    }

    public function test_outsiders_cannot_preview_or_export(): void
    {
        [$workspace] = $this->workspace();
        [, $outsider] = $this->workspace();
        $qrCode = $this->directQrCode($workspace);
        Sanctum::actingAs($outsider);

        $this->get('/api/v1/qr-codes/'.$qrCode->token.'/preview')->assertForbidden();
        $this->get('/api/v1/qr-codes/'.$qrCode->token.'/export/svg?size=1')->assertForbidden();
    }

    private function expectPreviewOf(string $content): void
    {
        $this->mock(QrCodeRenderer::class, function (MockInterface $mock) use ($content): void {
            $mock->shouldReceive('svg')
                ->once()
                ->with(Mockery::type(QrCode::class), $content)
                ->andReturn('<svg />');
        });
    }

    private function directQrCode(Workspace $workspace): QrCode
    {
        return $workspace->qrCodes()->create([
            'name' => 'Note',
            'token' => 'direct-'.str()->random(8),
            'payload_type' => 'text',
            'payload' => ['text' => 'Original text'],
            'content' => 'Original text',
        ]);
    }

    private function workspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Events',
            'slug' => 'events-'.strtolower(str()->random(6)),
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
