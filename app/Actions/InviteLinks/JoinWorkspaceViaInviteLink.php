<?php

namespace App\Actions\InviteLinks;

use App\Models\InviteLink;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;

class JoinWorkspaceViaInviteLink
{
    /**
     * Add the user to the invite link's workspace. Existing members keep
     * their current role and do not consume a use.
     */
    public function handle(User $user, InviteLink $inviteLink): WorkspaceMember
    {
        return DB::transaction(function () use ($user, $inviteLink) {
            $link = InviteLink::query()->whereKey($inviteLink->id)->lockForUpdate()->firstOrFail();
            abort_unless($link->isUsable(), 410);

            $member = WorkspaceMember::query()
                ->where('workspace_id', $link->workspace_id)
                ->where('user_id', $user->id)
                ->first();

            if ($member) {
                return $member;
            }

            $link->increment('uses');

            return WorkspaceMember::create([
                'workspace_id' => $link->workspace_id,
                'user_id' => $user->id,
                'role' => $link->role,
            ]);
        });
    }
}
