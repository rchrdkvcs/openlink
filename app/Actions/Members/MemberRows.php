<?php

namespace App\Actions\Members;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;

class MemberRows
{
    public static function for(Workspace $workspace): Collection
    {
        return $workspace->members()
            ->with('user.profileAvatarSource')
            ->orderBy('role')
            ->get()
            ->map(fn (WorkspaceMember $member) => [
                'id' => $member->id,
                'role' => $member->role,
                'created_at' => $member->created_at,
                'user' => [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'profile_avatar_url' => $member->user->profileAvatarUrl(),
                ],
            ]);
    }
}
