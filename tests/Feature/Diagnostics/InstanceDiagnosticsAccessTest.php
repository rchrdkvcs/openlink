<?php

namespace Tests\Feature\Diagnostics;

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class InstanceDiagnosticsAccessTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_only_an_instance_admin_can_open_instance_diagnostics(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $admin = User::factory()->create(['is_instance_admin' => true]);
        $this->workspaceFor($admin);

        $this->actingAs($owner)
            ->withHeader('Host', 'localhost')
            ->get(route('settings.diagnostics'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->withHeader('Host', 'localhost')
            ->get(route('settings.diagnostics'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Diagnostics')
                ->has('checks.database')
                ->has('checks.redis')
                ->has('checks.worker')
                ->has('checks.scheduler')
                ->has('checks.mail')
                ->has('checks.storage')
                ->has('checks.analytics'));
    }

    public function test_guests_are_sent_to_login_and_cannot_trigger_checks(): void
    {
        $this->withHeader('Host', 'localhost')
            ->get(route('settings.diagnostics'))
            ->assertRedirect(route('login'));

        $this->withHeader('Host', 'localhost')
            ->post(route('instance-diagnostics.recheck'))
            ->assertRedirect(route('login'));

        $this->withHeader('Host', 'localhost')
            ->post(route('instance-diagnostics.test-mail'))
            ->assertRedirect(route('login'));
    }

    public function test_workspace_admins_cannot_trigger_checks_or_test_emails(): void
    {
        [$workspace] = $this->workspaceWithOwner();
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => WorkspaceMember::ROLE_ADMIN]);

        $this->actingAs($member)
            ->withHeader('Host', 'localhost')
            ->post(route('instance-diagnostics.recheck'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withHeader('Host', 'localhost')
            ->post(route('instance-diagnostics.test-mail'))
            ->assertForbidden();

        $this->assertDatabaseCount('diagnostic_observations', 0);
    }
}
