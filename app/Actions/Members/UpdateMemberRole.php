<?php

namespace App\Actions\Members;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Access\Gate;

class UpdateMemberRole
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, WorkspaceMember $member, WorkspaceRole $role): WorkspaceMember
    {
        $this->gate->forUser($actor)->authorize('update', $member);

        $member->update(['role' => $role->ensureAssignable()->value]);

        return $member;
    }
}
