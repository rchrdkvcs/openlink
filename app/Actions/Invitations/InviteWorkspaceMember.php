<?php

namespace App\Actions\Invitations;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Invitation;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteWorkspaceMember
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /**
     * @return array{member: ?WorkspaceMember, invitation: ?Invitation}
     */
    public function handle(Request $request, string $email, string $role): array
    {
        $workspace = $this->access->requireManagedWorkspace($request);

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
            'invited_by_id' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation->load('workspace')));

        return ['member' => null, 'invitation' => $invitation];
    }
}
