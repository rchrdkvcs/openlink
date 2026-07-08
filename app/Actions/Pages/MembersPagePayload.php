<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Models\User;
use App\Models\Workspace;

class MembersPagePayload
{
    public function __construct(
        private readonly WorkspaceShellPayload $shell,
        private readonly WorkspaceAccess $access,
        private readonly WorkspacePayloads $workspacePayloads,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        $canManage = $this->access->canManageWorkspace($user, $workspace);

        return [
            ...$this->shell->handle($workspace, $user),
            'members' => $workspace->members()
                ->with('user.profileAvatarSource')
                ->orderBy('role')
                ->get()
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'role' => $member->role,
                    'created_at' => $member->created_at,
                    'user' => [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'profile_avatar_url' => $member->user->profileAvatarUrl(),
                    ],
                ]),
            'inviteLinks' => $canManage ? $this->workspacePayloads->inviteLinks($workspace) : [],
        ];
    }
}
