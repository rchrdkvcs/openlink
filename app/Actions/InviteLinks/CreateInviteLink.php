<?php

namespace App\Actions\InviteLinks;

use App\Enums\WorkspaceRole;
use App\Models\InviteLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Str;

class CreateInviteLink
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $creator, Workspace $workspace, WorkspaceRole $role, ?int $expiresInDays, ?int $maxUses): InviteLink
    {
        $this->gate->forUser($creator)->authorize('manage', $workspace);

        return InviteLink::create([
            'workspace_id' => $workspace->id,
            'created_by_id' => $creator->id,
            'role' => $role->ensureAssignable()->value,
            'token' => Str::random(48),
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            'max_uses' => $maxUses,
        ]);
    }
}
