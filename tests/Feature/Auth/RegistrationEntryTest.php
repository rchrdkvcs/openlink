<?php

namespace Tests\Feature\Auth;

use App\Models\Domain;
use App\Models\InviteLink;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RegistrationEntryTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public function test_first_registration_creates_instance_admin_and_allows_onboarding_by_default(): void
    {
        $this->post('/register', [
            'name' => 'Bear',
            'email' => 'bear@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'bear@example.com')->firstOrFail();

        $this->assertTrue($user->is_instance_admin);
        $this->assertDatabaseHas('domains', ['hostname' => 'localhost', 'status' => Domain::STATUS_ACTIVE, 'is_default' => true]);
        $this->assertDatabaseCount('workspaces', 0);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.show', absolute: false));

        $this->actingAs($user)->post(route('onboarding.workspace'), ['name' => 'Bear Co'])
            ->assertRedirect(route('onboarding.show', absolute: false));

        $this->assertDatabaseHas('workspaces', ['name' => 'Bear Co', 'owner_id' => $user->id]);
        $this->assertDatabaseHas('workspace_members', ['user_id' => $user->id, 'role' => WorkspaceMember::ROLE_OWNER]);
    }

    public function test_invite_link_allows_registration_in_invite_only_mode(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain();

        $this->actingAs($owner)->post(route('invite-links.store'), [
            'role' => WorkspaceMember::ROLE_EDITOR,
        ])->assertRedirect();

        $link = InviteLink::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->post('/logout');

        $this->get(route('join.show', $link))->assertOk();
        $this->get(route('register', ['invite' => $link->token]))->assertOk();

        $this->post('/register', [
            'name' => 'Invited Editor',
            'email' => 'editor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_token' => $link->token,
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'editor@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertSame(1, $link->fresh()->uses);
    }
}
