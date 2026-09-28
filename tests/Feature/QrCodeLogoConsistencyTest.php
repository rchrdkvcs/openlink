<?php

namespace Tests\Feature;

use App\Actions\QrCodes\DeleteQrCode;
use App\Actions\QrCodes\UpdateQrCode;
use App\Models\QrCode;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class QrCodeLogoConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_logo_replacement_keeps_the_existing_file_and_removes_the_new_upload(): void
    {
        Storage::fake();
        [$user, $qrCode, $oldLogoPath] = $this->qrCodeWithLogo();
        $request = $this->requestFor($user);
        $request->files->set('logo', UploadedFile::fake()->create('replacement.png', 10, 'image/png'));

        Event::listen('eloquent.saving: '.QrCode::class, fn () => throw new RuntimeException('save failed'));

        try {
            app(UpdateQrCode::class)->handle($request, $qrCode, ['name' => 'Changed']);
            $this->fail('The QR Code save should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('save failed', $exception->getMessage());
        } finally {
            Event::forget('eloquent.saving: '.QrCode::class);
        }

        $this->assertSame($oldLogoPath, $qrCode->fresh()->logo_path);
        $this->assertSame('Original', $qrCode->fresh()->name);
        $this->assertSame([$oldLogoPath], Storage::allFiles('qr-logos'));
    }

    public function test_failed_qr_code_deletion_keeps_its_logo(): void
    {
        Storage::fake();
        [$user, $qrCode, $oldLogoPath] = $this->qrCodeWithLogo();

        Event::listen('eloquent.deleting: '.QrCode::class, fn () => throw new RuntimeException('delete failed'));

        try {
            app(DeleteQrCode::class)->handle($this->requestFor($user), $qrCode);
            $this->fail('The QR Code deletion should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('delete failed', $exception->getMessage());
        } finally {
            Event::forget('eloquent.deleting: '.QrCode::class);
        }

        $this->assertNotNull($qrCode->fresh());
        Storage::assertExists($oldLogoPath);
    }

    /** @return array{User, QrCode, string} */
    private function qrCodeWithLogo(): array
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

        $oldLogoPath = 'qr-logos/original.png';
        Storage::put($oldLogoPath, 'original');
        $qrCode = QrCode::create([
            'workspace_id' => $workspace->id,
            'name' => 'Original',
            'token' => 'logo-consistency-token',
            'payload_type' => 'text',
            'payload' => ['text' => 'example'],
            'content' => 'example',
            'logo_path' => $oldLogoPath,
        ]);

        return [$user, $qrCode, $oldLogoPath];
    }

    private function requestFor(User $user): Request
    {
        $request = Request::create('/qr-codes/logo-consistency-token', 'PATCH');
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
