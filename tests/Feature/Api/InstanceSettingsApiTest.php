<?php

namespace Tests\Feature\Api;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstanceSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_settings_without_dns_target_preserves_it(): void
    {
        $settings = app(InstanceSettings::class);
        $settings->set('dns_target', '203.0.113.10');
        $settings->set('reserved_slugs', ['promo']);
        $this->actAsInstanceAdmin();

        $this->patchJson('/api/v1/instance-settings', $this->requiredFields())
            ->assertOk()
            ->assertJsonPath('data.dns_target', '203.0.113.10')
            ->assertJsonPath('data.reserved_slugs', ['promo'])
            ->assertJsonPath('data.slug_length', 8);
    }

    public function test_dns_target_can_be_updated_and_cleared_through_the_api(): void
    {
        $this->actAsInstanceAdmin();

        $this->patchJson('/api/v1/instance-settings', [...$this->requiredFields(), 'dns_target' => ' app.example.test '])
            ->assertOk()
            ->assertJsonPath('data.dns_target', 'app.example.test');

        $this->patchJson('/api/v1/instance-settings', [...$this->requiredFields(), 'dns_target' => null])
            ->assertOk()
            ->assertJsonPath('data.dns_target', '');
    }

    public function test_api_and_web_share_validation(): void
    {
        $admin = $this->actAsInstanceAdmin();

        $this->patchJson('/api/v1/instance-settings', [...$this->requiredFields(), 'dns_target' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dns_target');

        $this->actingAs($admin)
            ->patch(route('instance-settings.update'), [...$this->requiredFields(), 'dns_target' => str_repeat('a', 256)])
            ->assertSessionHasErrors('dns_target');
    }

    public function test_updating_settings_makes_the_default_domain_active(): void
    {
        $this->actAsInstanceAdmin();

        $this->patchJson('/api/v1/instance-settings', [...$this->requiredFields(), 'default_domain' => 'Go.Example.test'])
            ->assertOk();

        $domain = Domain::query()->where('is_default', true)->sole();
        $this->assertSame('go.example.test', $domain->hostname);
        $this->assertSame(DomainStatus::Active, $domain->status);
    }

    private function actAsInstanceAdmin(): User
    {
        $admin = User::factory()->create(['is_instance_admin' => true]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function requiredFields(): array
    {
        return [
            'registration_mode' => 'invite_only',
            'require_email_verification' => false,
            'default_domain' => 'localhost',
            'slug_length' => 8,
            'analytics_retention_days' => 365,
            'public_unavailable_title' => 'Unavailable',
            'public_unavailable_message' => 'Gone.',
        ];
    }
}
