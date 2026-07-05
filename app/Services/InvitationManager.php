<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationManager
{
    /**
     * Invite an email to a workspace: existing users become members
     * immediately, unknown emails receive an invitation.
     *
     * @return array{member: ?WorkspaceMember, invitation: ?Invitation}
     */
    public function invite(Workspace $workspace, User $invitedBy, string $email, string $role): array
    {
        if ($user = User::query()->where('email', $email)->first()) {
            $member = WorkspaceMember::query()->updateOrCreate([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ], ['role' => $role]);

            return ['member' => $member, 'invitation' => null];
        }

        $invitation = Invitation::query()->updateOrCreate([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'accepted_at' => null,
        ], [
            'role' => $role,
            'token' => Str::random(48),
            'invited_by_id' => $invitedBy->id,
            'expires_at' => now()->addDays(14),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation->load('workspace')));

        return ['member' => null, 'invitation' => $invitation];
    }

    public function accept(User $user, Invitation $invitation): WorkspaceMember
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
