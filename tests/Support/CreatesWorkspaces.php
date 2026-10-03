<?php

namespace Tests\Support;

use App\Models\Domain;
use App\Models\InviteLink;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Str;

trait CreatesWorkspaces
{
    protected function workspaceFor(User $user, string $name = 'Events', ?string $slug = null, string $role = WorkspaceMember::ROLE_OWNER): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $name,
            'slug' => $slug ?? Str::slug($name).'-'.strtolower(Str::random(6)),
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $workspace;
    }

    protected function workspaceWithOwner(string $name = 'Events', ?string $slug = null): array
    {
        $owner = User::factory()->create();

        return [$this->workspaceFor($owner, $name, $slug), $owner];
    }

    protected function workspaceWithActiveDomain(string $hostname = 'localhost'): array
    {
        [$workspace, $owner] = $this->workspaceWithOwner();
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.Str::random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $owner];
    }

    protected function memberOf(Workspace $workspace, string $role): User
    {
        $user = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $user;
    }

    protected function membershipOf(Workspace $workspace, User $user): WorkspaceMember
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    protected function shortLink(Workspace $workspace, Domain $domain, string $slug, array $attributes = []): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => 'https://example.com/landing',
            ...$attributes,
        ]);
    }

    protected function inviteLink(Workspace $workspace, User $creator, string $role = WorkspaceMember::ROLE_EDITOR): InviteLink
    {
        return InviteLink::create([
            'workspace_id' => $workspace->id,
            'created_by_id' => $creator->id,
            'role' => $role,
            'token' => Str::random(48),
        ]);
    }

    protected function actingInWorkspace(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession(['workspace_id' => $workspace->id]);
    }
}
