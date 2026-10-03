<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class QrCodeLogoCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_permanently_deleting_a_short_link_removes_its_qr_code_logos(): void
    {
        [$workspace, $link] = $this->linkedWorkspace();
        $linked = $this->qrCodeWithLogo($workspace, $link, 'linked');
        $direct = $this->qrCodeWithLogo($workspace, null, 'direct');

        $link->delete();

        $this->assertNull($linked['qr']->fresh());
        Storage::assertMissing($linked['path']);
        Storage::assertExists($direct['path']);
    }

    public function test_deleting_a_workspace_removes_all_its_qr_code_logos(): void
    {
        [$workspace, $link] = $this->linkedWorkspace();
        [$other] = $this->linkedWorkspace();
        $linked = $this->qrCodeWithLogo($workspace, $link, 'linked');
        $direct = $this->qrCodeWithLogo($workspace, null, 'direct');
        $kept = $this->qrCodeWithLogo($other, null, 'kept');

        $workspace->delete();

        Storage::assertMissing($linked['path']);
        Storage::assertMissing($direct['path']);
        Storage::assertExists($kept['path']);
    }

    public function test_deleting_a_domain_removes_logos_of_its_short_link_qr_codes(): void
    {
        [$workspace, $link] = $this->linkedWorkspace();
        $linked = $this->qrCodeWithLogo($workspace, $link, 'linked');

        $link->domain->delete();

        Storage::assertMissing($linked['path']);
    }

    public function test_deleting_an_account_removes_logos_of_owned_workspaces(): void
    {
        [$workspace] = $this->linkedWorkspace();
        $direct = $this->qrCodeWithLogo($workspace, null, 'direct');

        $workspace->owner->delete();

        Storage::assertMissing($direct['path']);
    }

    public function test_a_failed_short_link_deletion_keeps_qr_code_logos(): void
    {
        [$workspace, $link] = $this->linkedWorkspace();
        $linked = $this->qrCodeWithLogo($workspace, $link, 'linked');

        Event::listen('eloquent.deleting: '.ShortLink::class, fn () => throw new RuntimeException('delete failed'));

        try {
            $link->delete();
            $this->fail('The Short Link deletion should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('delete failed', $exception->getMessage());
        } finally {
            Event::forget('eloquent.deleting: '.ShortLink::class);
        }

        Storage::assertExists($linked['path']);
    }

    private function linkedWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Events',
            'slug' => 'events-'.strtolower(str()->random(6)),
            'settings' => [],
        ]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceMember::ROLE_OWNER]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'go-'.strtolower(str()->random(6)).'.example.test',
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

        return [$workspace, $link];
    }

    private function qrCodeWithLogo(Workspace $workspace, ?ShortLink $link, string $name): array
    {
        $path = 'qr-logos/'.$name.'-'.str()->random(6).'.png';
        Storage::put($path, 'logo');

        $qrCode = $workspace->qrCodes()->create([
            'name' => $name,
            'token' => $name.'-'.str()->random(8),
            'short_link_id' => $link?->id,
            'payload_type' => $link ? null : 'text',
            'payload' => $link ? null : ['text' => $name],
            'content' => $link ? null : $name,
            'logo_path' => $path,
        ]);

        return ['qr' => $qrCode, 'path' => $path];
    }
}
