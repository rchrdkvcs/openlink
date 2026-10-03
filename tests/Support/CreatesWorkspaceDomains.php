<?php

namespace Tests\Support;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Dns\DnsLookup;

trait CreatesWorkspaceDomains
{
    protected function workspaceWithDomain(string $hostname = 'go.example.test', DomainStatus $status = DomainStatus::Pending): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Events',
            'slug' => 'events-'.strtolower(str()->random(6)),
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => $status,
            'verification_token' => 'test-token-'.str()->random(12),
        ]);

        return [$workspace, $domain, $user];
    }

    protected function fakeDns(): InMemoryDnsLookup
    {
        $dns = new InMemoryDnsLookup;
        $this->app->instance(DnsLookup::class, $dns);

        return $dns;
    }
}
