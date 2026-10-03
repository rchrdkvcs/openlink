<?php

namespace Tests\Feature\QrCodes;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesQrCodes;
use Tests\TestCase;

class QrCodeTargetTest extends TestCase
{
    use CreatesQrCodes;
    use RefreshDatabase;

    public function test_scan_through_the_qr_entry_redirects_to_the_destination(): void
    {
        [, , , $qrCode] = $this->linkWithQrCode();

        $this->get('/qr/'.$qrCode->token)
            ->assertRedirect('https://example.com/destination');
    }

    public function test_qr_code_can_switch_between_a_short_link_and_a_direct_payload(): void
    {
        [$workspace, , $user, $qrCode, $link] = $this->linkWithQrCode();
        $token = $qrCode->token;

        $this->actingAs($user)->patch(route('qr-codes.update', $qrCode), [
            'short_link_id' => null,
            'payload_type' => 'url',
            'payload' => ['url' => 'https://example.com/direct'],
        ])->assertRedirect();

        $qrCode->refresh();
        $this->assertNull($qrCode->short_link_id);
        $this->assertSame($workspace->id, $qrCode->workspace_id);
        $this->assertSame('https://example.com/direct', $qrCode->content);
        $this->assertSame($token, $qrCode->token);

        $this->actingAs($user)->patch(route('qr-codes.update', $qrCode), [
            'short_link_id' => $link->id,
        ])->assertRedirect();

        $qrCode->refresh();
        $this->assertSame($link->id, $qrCode->short_link_id);
        $this->assertNull($qrCode->payload_type);
        $this->assertNull($qrCode->payload);
        $this->assertNull($qrCode->content);
        $this->assertSame($token, $qrCode->token);
    }

    public function test_qr_code_cannot_link_to_a_short_link_from_another_workspace(): void
    {
        [, , $user, $qrCode] = $this->linkWithQrCode();
        $otherWorkspace = $this->workspaceFor(User::factory()->create(), 'Other', 'other-target');
        $otherDomain = Domain::create([
            'workspace_id' => $otherWorkspace->id,
            'hostname' => 'other.example.test',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'other-token',
            'verified_at' => now(),
        ]);
        $otherLink = $this->shortLink($otherWorkspace, $otherDomain, 'private', ['destination_url' => 'https://example.com/private']);

        $this->actingAs($user)->patch(route('qr-codes.update', $qrCode), [
            'short_link_id' => $otherLink->id,
        ])->assertSessionHasErrors('short_link_id');

        $this->assertNotSame($otherLink->id, $qrCode->fresh()->short_link_id);
    }
}
