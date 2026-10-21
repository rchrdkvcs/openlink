<?php

namespace Tests\Feature\QrCodes;

use App\Models\QrCode;
use App\Models\QrCodeTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\QrCodes\Rendering\QrGrid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesQrCodes;
use Tests\TestCase;

class QrCodeTemplateTest extends TestCase
{
    use CreatesQrCodes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_owner_saves_a_template_from_a_qr_code_design_only(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $this->styled($qrCode);

        $this->actingAs($owner)
            ->post(route('qr-code-templates.store'), ['name' => 'Brand', 'qr_code' => $qrCode->token])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template = $workspace->qrCodeTemplates()->sole();
        $this->assertSame([
            'workspace_id' => $workspace->id,
            'name' => 'Brand',
            'foreground_color' => '#123456',
            'background_color' => '#fefefe',
            'background_transparent' => true,
            'margin' => 4,
            'error_correction' => 'high',
            'style' => 'dot',
            'eye_style' => 'circle',
        ], $template->only(['workspace_id', 'name', ...QrCodeTemplate::APPEARANCE]));
        $this->assertEqualsCanonicalizing(
            ['id', 'workspace_id', 'name', ...QrCodeTemplate::APPEARANCE, 'logo_path', 'created_at', 'updated_at'],
            array_keys($template->getAttributes()),
        );
        $this->assertNotSame($qrCode->logo_path, $template->logo_path);
        Storage::assertExists($template->logo_path);
        $this->assertSame(Storage::get($qrCode->logo_path), Storage::get($template->logo_path));
    }

    public function test_admin_manages_templates(): void
    {
        [$workspace, , , $qrCode] = $this->linkWithQrCode();
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingAs($admin)
            ->post(route('qr-code-templates.store'), ['name' => 'Brand', 'qr_code' => $qrCode->token])
            ->assertSessionHasNoErrors();
        $template = $workspace->qrCodeTemplates()->sole();

        $this->actingAs($admin)
            ->patch(route('qr-code-templates.update', $template), ['name' => 'Print'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Print', $template->refresh()->name);

        $this->actingAs($admin)->delete(route('qr-code-templates.destroy', $template))->assertRedirect();
        $this->assertModelMissing($template);
    }

    public function test_editors_and_viewers_cannot_manage_templates(): void
    {
        [$workspace, , , $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace);

        foreach ([WorkspaceMember::ROLE_EDITOR, WorkspaceMember::ROLE_VIEWER] as $role) {
            $member = $this->memberOf($workspace, $role);

            $this->actingAs($member)
                ->post(route('qr-code-templates.store'), ['name' => 'Mine', 'qr_code' => $qrCode->token])
                ->assertForbidden();
            $this->actingAs($member)
                ->patch(route('qr-code-templates.update', $template), ['name' => 'Renamed'])
                ->assertForbidden();
            $this->actingAs($member)->delete(route('qr-code-templates.destroy', $template))->assertForbidden();
        }

        $this->assertSame(['Brand'], $workspace->qrCodeTemplates()->pluck('name')->all());
    }

    public function test_templates_are_scoped_to_the_current_workspace(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $outsider = User::factory()->create();
        $other = $this->workspaceFor($outsider, 'Other', 'other');
        $foreignQrCode = $this->directQrCode($other);
        $foreignTemplate = $this->template($other, 'Foreign', $this->storedLogo());

        $this->actingAs($owner)
            ->post(route('qr-code-templates.store'), ['name' => 'Stolen', 'qr_code' => $foreignQrCode->token])
            ->assertSessionHasErrors('qr_code');
        $this->actingAs($owner)
            ->patch(route('qr-code-templates.update', $foreignTemplate), ['name' => 'Hijack'])
            ->assertForbidden();
        $this->actingAs($owner)->delete(route('qr-code-templates.destroy', $foreignTemplate))->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('qr-codes.update', $qrCode), ['logo_template_id' => $foreignTemplate->id])
            ->assertSessionHasErrors('logo_template_id');
        $this->actingAs($owner)
            ->get(route('qr-codes.preview', [$qrCode, 'logo_template_id' => $foreignTemplate->id]))
            ->assertSessionHasErrors('logo_template_id');

        $this->assertSame('Foreign', $foreignTemplate->refresh()->name);
        $this->assertFalse($qrCode->refresh()->hasLogo());
        $this->assertSame(0, $workspace->qrCodeTemplates()->count());
    }

    public function test_template_input_is_validated(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $this->template($workspace);

        $this->actingAs($owner)
            ->post(route('qr-code-templates.store'), ['name' => '', 'qr_code' => 'missing-token'])
            ->assertSessionHasErrors(['name', 'qr_code']);
        $this->actingAs($owner)
            ->post(route('qr-code-templates.store'), ['name' => 'Brand', 'qr_code' => $qrCode->token])
            ->assertSessionHasErrors('name');
        $this->actingAs($owner)
            ->post(route('qr-code-templates.store'), ['name' => str_repeat('a', 81), 'qr_code' => $qrCode->token])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $workspace->qrCodeTemplates()->count());
    }

    public function test_studio_lists_templates_only_for_members_who_can_apply_them(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $this->template($workspace, 'Brand', $this->storedLogo());
        $this->template($workspace, 'Archive');
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAs($owner)
            ->get(route('qr-codes.show', $qrCode))
            ->assertInertia(fn ($page) => $page
                ->has('templates', 2)
                ->where('templates.0.name', 'Archive')
                ->where('templates.1.name', 'Brand')
                ->where('templates.1.has_logo', true)
                ->where('templates.1.foreground_color', '#0f172a')
                ->missing('templates.1.logo_path'));

        $this->actingAs($viewer)
            ->get(route('qr-codes.show', $qrCode))
            ->assertInertia(fn ($page) => $page->has('templates', 0));
    }

    public function test_applying_a_template_copies_its_design_and_keeps_the_target_and_entry_url(): void
    {
        [$workspace, , , $qrCode, $link] = $this->linkWithQrCode();
        $editor = $this->memberOf($workspace, WorkspaceMember::ROLE_EDITOR);
        $template = $this->template($workspace, 'Brand', $this->storedLogo());
        $publicUrl = $qrCode->publicUrl();

        $this->actingAs($editor)
            ->patch(route('qr-codes.update', $qrCode), [
                ...$template->only(QrCodeTemplate::APPEARANCE),
                'logo_template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $qrCode->refresh();
        $this->assertSame('Poster', $qrCode->name);
        $this->assertSame('studio-qr-token', $qrCode->token);
        $this->assertSame($link->id, $qrCode->short_link_id);
        $this->assertSame($publicUrl, $qrCode->publicUrl());
        $this->assertSame($template->only(QrCodeTemplate::APPEARANCE), $qrCode->only(QrCodeTemplate::APPEARANCE));
        $this->assertNotSame($template->logo_path, $qrCode->logo_path);
        $this->assertSame(Storage::get($template->logo_path), Storage::get($qrCode->logo_path));
    }

    public function test_applying_a_template_keeps_a_direct_payload(): void
    {
        [$workspace, , $owner] = $this->linkWithQrCode();
        $qrCode = $this->directQrCode($workspace);
        $template = $this->template($workspace, 'Brand', $this->storedLogo());

        $this->actingAs($owner)
            ->patch(route('qr-codes.update', $qrCode), [
                ...$template->only(QrCodeTemplate::APPEARANCE),
                'logo_template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $qrCode->refresh();
        $this->assertSame('text', $qrCode->payload_type);
        $this->assertSame(['text' => 'Hello'], $qrCode->payload);
        $this->assertSame('Hello', $qrCode->encodedContent());
        $this->assertTrue($qrCode->hasLogo());
    }

    public function test_viewers_cannot_apply_templates(): void
    {
        [$workspace, , , $qrCode] = $this->linkWithQrCode();
        $viewer = $this->memberOf($workspace, WorkspaceMember::ROLE_VIEWER);
        $template = $this->template($workspace, 'Brand', $this->storedLogo());

        $this->actingAs($viewer)
            ->patch(route('qr-codes.update', $qrCode), [
                ...$template->only(QrCodeTemplate::APPEARANCE),
                'logo_template_id' => $template->id,
            ])
            ->assertForbidden();

        $this->assertFalse($qrCode->refresh()->hasLogo());
        $this->assertSame('#111827', $qrCode->foreground_color);
    }

    public function test_applied_designs_keep_existing_validation(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace, 'Brand', $this->storedLogo());
        $bare = $this->template($workspace, 'Bare');

        $this->actingAs($owner)
            ->patch(route('qr-codes.update', $qrCode), ['foreground_color' => 'red', 'logo_template_id' => $template->id])
            ->assertSessionHasErrors('foreground_color');
        $this->actingAs($owner)
            ->patch(route('qr-codes.update', $qrCode), ['logo_template_id' => $bare->id])
            ->assertSessionHasErrors('logo_template_id');

        $this->assertFalse($qrCode->refresh()->hasLogo());
    }

    public function test_a_template_logo_still_raises_low_error_correction(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace, 'Brand', $this->storedLogo(), ['error_correction' => 'low']);

        $this->actingAs($owner)
            ->patch(route('qr-codes.update', $qrCode), [
                ...$template->only(QrCodeTemplate::APPEARANCE),
                'logo_template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $qrCode->refresh();
        $content = $qrCode->encodedContent();
        $this->assertSame('low', $qrCode->error_correction);
        $this->assertSame(
            QrGrid::for(new QrCode(['error_correction' => 'quartile', 'margin' => 0, 'size' => 512]), $content)->modules,
            QrGrid::for($qrCode, $content)->modules,
        );
        $this->assertNotSame(
            QrGrid::for(new QrCode(['error_correction' => 'low', 'margin' => 0, 'size' => 512]), $content)->modules,
            QrGrid::for($qrCode, $content)->modules,
        );
    }

    public function test_preview_shows_a_template_logo_without_persisting_it(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace, 'Brand', $this->storedLogo());

        $withoutLogo = $this->actingAs($owner)->get(route('qr-codes.preview', $qrCode))->assertOk()->getContent();
        $withLogo = $this->actingAs($owner)
            ->get(route('qr-codes.preview', [$qrCode, 'logo_template_id' => $template->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<image', $withoutLogo);
        $this->assertStringContainsString('<image', $withLogo);
        $this->assertFalse($qrCode->refresh()->hasLogo());
    }

    public function test_preview_hides_a_logo_pending_removal(): void
    {
        [, , $owner, $qrCode] = $this->linkWithQrCode();
        $qrCode->update(['logo_path' => $this->storedLogo()]);

        $kept = $this->actingAs($owner)->get(route('qr-codes.preview', $qrCode))->getContent();
        $removed = $this->actingAs($owner)->get(route('qr-codes.preview', [$qrCode, 'remove_logo' => 1]))->getContent();

        $this->assertStringContainsString('<image', $kept);
        $this->assertStringNotContainsString('<image', $removed);
        $this->assertTrue($qrCode->refresh()->hasLogo());
    }

    public function test_updating_a_template_does_not_change_qr_codes_that_applied_it(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace, 'Brand', $this->storedLogo());
        $this->apply($owner, $qrCode, $template);
        $applied = $qrCode->refresh()->only([...QrCodeTemplate::APPEARANCE, 'logo_path']);
        $source = $this->styled($this->directQrCode($workspace));
        $previousTemplateLogo = $template->logo_path;

        $this->actingAs($owner)
            ->patch(route('qr-code-templates.update', $template), ['qr_code' => $source->token])
            ->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('Brand', $template->name);
        $this->assertSame('#123456', $template->foreground_color);
        $this->assertNotSame($source->logo_path, $template->logo_path);
        Storage::assertMissing($previousTemplateLogo);
        Storage::assertExists($template->logo_path);
        $this->assertSame($applied, $qrCode->refresh()->only([...QrCodeTemplate::APPEARANCE, 'logo_path']));
        Storage::assertExists($qrCode->logo_path);
    }

    public function test_replacing_a_template_design_with_a_logo_free_qr_code_removes_its_logo(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace, 'Brand', $this->storedLogo());
        $previous = $template->logo_path;

        $this->actingAs($owner)
            ->patch(route('qr-code-templates.update', $template), ['qr_code' => $qrCode->token])
            ->assertSessionHasNoErrors();

        $this->assertFalse($template->refresh()->hasLogo());
        Storage::assertMissing($previous);
    }

    public function test_deleting_a_template_keeps_logos_of_qr_codes_that_applied_it(): void
    {
        [$workspace, , $owner, $qrCode] = $this->linkWithQrCode();
        $template = $this->template($workspace, 'Brand', $this->storedLogo());
        $this->apply($owner, $qrCode, $template);
        $templateLogo = $template->logo_path;

        $this->actingAs($owner)->delete(route('qr-code-templates.destroy', $template))->assertRedirect();

        Storage::assertMissing($templateLogo);
        Storage::assertExists($qrCode->refresh()->logo_path);
    }

    public function test_deleting_a_source_qr_code_keeps_the_template_logo(): void
    {
        [$workspace, , $owner] = $this->linkWithQrCode();
        $source = $this->styled($this->directQrCode($workspace));
        $this->actingAs($owner)->post(route('qr-code-templates.store'), ['name' => 'Brand', 'qr_code' => $source->token]);
        $template = $workspace->qrCodeTemplates()->sole();
        $sourceLogo = $source->logo_path;

        $this->actingAs($owner)->delete(route('qr-codes.destroy', $source))->assertRedirect();

        Storage::assertMissing($sourceLogo);
        Storage::assertExists($template->logo_path);
    }

    public function test_deleting_a_workspace_or_its_owner_removes_template_logos(): void
    {
        [$workspace] = $this->linkWithQrCode();
        [$other, $otherOwner] = $this->workspaceWithOwner('Other');
        $removed = $this->template($workspace, 'Brand', $this->storedLogo());
        $owned = $this->template($other, 'Brand', $this->storedLogo());

        $workspace->delete();
        Storage::assertMissing($removed->logo_path);
        Storage::assertExists($owned->logo_path);

        $otherOwner->delete();
        Storage::assertMissing($owned->logo_path);
    }

    private function apply(User $user, QrCode $qrCode, QrCodeTemplate $template): void
    {
        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), [
                ...$template->only(QrCodeTemplate::APPEARANCE),
                'logo_template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();
    }

    private function styled(QrCode $qrCode): QrCode
    {
        $qrCode->update([
            'foreground_color' => '#123456',
            'background_color' => '#fefefe',
            'background_transparent' => true,
            'margin' => 4,
            'error_correction' => 'high',
            'style' => 'dot',
            'eye_style' => 'circle',
            'logo_path' => $this->storedLogo(),
        ]);

        return $qrCode->refresh();
    }

    private function template(Workspace $workspace, string $name = 'Brand', ?string $logoPath = null, array $attributes = []): QrCodeTemplate
    {
        return $workspace->qrCodeTemplates()->create([
            'name' => $name,
            'foreground_color' => '#0f172a',
            'background_color' => '#f8fafc',
            'background_transparent' => false,
            'margin' => 3,
            'error_correction' => 'quartile',
            'style' => 'rounded',
            'eye_style' => 'rounded',
            'logo_path' => $logoPath,
            ...$attributes,
        ])->refresh();
    }

    private function directQrCode(Workspace $workspace): QrCode
    {
        return $workspace->qrCodes()->create([
            'name' => 'Greeting',
            'token' => 'direct-'.$workspace->id,
            'payload_type' => 'text',
            'payload' => ['text' => 'Hello'],
            'content' => 'Hello',
        ])->refresh();
    }

    private function storedLogo(): string
    {
        return UploadedFile::fake()->createWithContent(
            'logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
        )->store('qr-logos');
    }
}
