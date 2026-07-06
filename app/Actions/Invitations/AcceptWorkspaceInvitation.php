<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use App\Models\WorkspaceMember;

class AcceptWorkspaceInvitation
{
    public function handle(User $user, Invitation $invitation): WorkspaceMember
    {
        abort_unless($user->email === $invitation->email, 403);
        abort_if($invitation->accepted_at || ($invitation->expires_at && $invitation->expires_at->isPast()), 410);

        $member = WorkspaceMember::query()->updateOrCreate([
            'workspace_id' => $invitation->workspace_id,
            'user_id' => $user->id,
        ], ['role' => $invitation->role]);

        $invitation->update(['accepted_at' => now()]);

        return $member;
    }
}
