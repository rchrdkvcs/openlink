<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_instance_admin_can_toggle_verification_and_recover_access(): void
    {
        $settings = app(InstanceSettings::class);
        $this->assertFalse($settings->get('require_email_verification'));

        $admin = User::factory()->unverified()->create(['is_instance_admin' => true]);
        $workspace = Workspace::create([
            'owner_id' => $admin->id,
            'name' => 'Test',
            'slug' => 'test',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $admin->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        $this->actingAs($admin)->withSession(['workspace_id' => $workspace->id]);
        $this->get(route('settings.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('settings.require_email_verification', false));

        $data = [
            ...$settings->all(),
            'reserved_slugs' => implode("\n", $settings->get('reserved_slugs')),
            'reserved_prefixes' => implode("\n", $settings->get('reserved_prefixes')),
            'require_email_verification' => true,
        ];

        $this->patch(route('instance-settings.update'), $data)->assertSessionHasNoErrors();
        $this->assertTrue($settings->get('require_email_verification'));
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice', absolute: false));
        $this->get(route('settings.index'))->assertOk();

        $this->patch(route('instance-settings.update'), [...$data, 'require_email_verification' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($settings->get('require_email_verification'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_unverified_instance_admin_can_disable_verification_through_api(): void
    {
        $settings = app(InstanceSettings::class);
        $settings->set('require_email_verification', true);
        $admin = User::factory()->unverified()->create(['is_instance_admin' => true]);
        Sanctum::actingAs($admin);

        $data = [
            ...$settings->all(),
            'reserved_slugs' => implode("\n", $settings->get('reserved_slugs')),
            'reserved_prefixes' => implode("\n", $settings->get('reserved_prefixes')),
            'require_email_verification' => false,
        ];

        $this->patchJson('/api/v1/instance-settings', $data)
            ->assertOk()
            ->assertJsonPath('data.require_email_verification', false);
    }
}
