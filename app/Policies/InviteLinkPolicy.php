<?php

namespace App\Policies;

use App\Models\InviteLink;
use App\Models\User;

class InviteLinkPolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function delete(User $user, InviteLink $inviteLink): bool
    {
        return $this->workspaces->manage($user, $inviteLink->workspace);
    }
}
