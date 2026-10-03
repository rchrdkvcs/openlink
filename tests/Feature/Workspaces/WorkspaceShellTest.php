<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_workspace_page_shares_the_shell_and_capabilities(): void
    {
        [$workspace, $owner] = $this->workspace();
        $admin = $this->member($workspace, WorkspaceMember::ROLE_ADMIN);

        foreach (['dashboard', 'links.index', 'domains.index', 'members.index', 'qr-codes.index', 'analytics.index'] as $route) {
            $this->actingAs($admin)
                ->withSession(['workspace_id' => $workspace->id])
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('currentWorkspace.id', $workspace->id)
                    ->has('workspaces', 1)
                    ->where('role', WorkspaceMember::ROLE_ADMIN)
                    ->where('canManageWorkspace', true)
                    ->where('canEditWorkspace', true)
                    ->has('navigation'));
        }

        $this->actingAs($owner)->get(route('members.index'))->assertInertia(fn ($page) => $page
            ->missing('canManageMembers')
            ->has('members', 2)
            ->has('inviteLinks'));
    }

    public function test_viewers_get_read_only_capabilities_and_no_invite_links(): void
    {
        [$workspace] = $this->workspace();
        $viewer = $this->member($workspace, WorkspaceMember::ROLE_VIEWER);

        $this->actingAs($viewer)
            ->withSession(['workspace_id' => $workspace->id])
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('role', WorkspaceMember::ROLE_VIEWER)
                ->where('canManageWorkspace', false)
                ->where('canEditWorkspace', false)
                ->where('inviteLinks', []));
    }

    public function test_the_shell_resolves_the_role_once_per_request(): void
    {
        [$workspace, $owner] = $this->workspace();

        $this->actingAs($owner)->get(route('domains.index'))->assertOk();

        DB::enableQueryLog();
        $this->get(route('domains.index'))->assertOk();
        $roleQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'select "role" from "workspace_members"'));

        $this->assertSame(2, $roleQueries->count());
    }

    public function test_pages_without_a_workspace_share_an_empty_shell(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('currentWorkspace', null)
                ->where('workspaces', [])
                ->where('role', null)
                ->where('canManageWorkspace', false)
                ->where('canEditWorkspace', false)
                ->where('navigation', null));
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => 'Shell',
            'slug' => 'shell',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        return [$workspace, $owner];
    }

    private function member(Workspace $workspace, string $role): User
    {
        $user = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
