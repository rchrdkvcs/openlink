<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_workspace_owner_can_render_dashboard(): void
    {
        [$workspace, $user] = $this->workspaceOwner();

        $this->actingAs($user)
            ->withHeader('Host', 'localhost')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('currentWorkspace.id', $workspace->id));
    }

    public function test_authenticated_workspace_owner_can_render_workspace_pages(): void
    {
        [, $user] = $this->workspaceOwner();

        foreach ([
            'dashboard' => 'Dashboard',
            'links.index' => 'Links/Index',
            'domains.index' => 'Domains/Index',
            'members.index' => 'Members/Index',
            'settings.index' => 'Settings/Index',
        ] as $route => $component) {
            $this->actingAs($user)
                ->withHeader('Host', 'localhost')
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }

    /** @return array{Workspace, User} */
    private function workspaceOwner(): array
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
        Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'localhost',
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'dashboard-test-token',
            'verified_at' => now(),
        ]);

        return [$workspace, $user];
    }
}
