<?php

namespace Tests\Feature\Api;

use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/links')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_main_api_routes_require_verified_email_but_profile_remains_available(): void
    {
        app(InstanceSettings::class)->set('require_email_verification', true);
        [$workspace, , $user] = $this->workspaceWithActiveDomain();
        $user->forceFill(['email_verified_at' => null])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('workspaces.0.id', $workspace->id);

        $this->getJson('/api/v1/links')
            ->assertForbidden()
            ->assertJsonPath('message', 'Verify your email address before using the API.');
    }

    public function test_profile_can_be_read_and_updated_via_api(): void
    {
        [$workspace, , $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('workspaces.0.id', $workspace->id)
            ->assertJsonPath('workspaces.0.role', WorkspaceMember::ROLE_OWNER);

        $this->patchJson('/api/v1/me', [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('user.name', 'Renamed');
    }

    public function test_instance_settings_require_instance_admin(): void
    {
        [, , $user] = $this->workspaceWithActiveDomain();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/instance-settings')->assertForbidden();

        $user->forceFill(['is_instance_admin' => true])->save();

        $this->getJson('/api/v1/instance-settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['registration_mode', 'default_domain', 'slug_length']]);
    }
}
