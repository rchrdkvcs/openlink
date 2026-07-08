<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceShellPayload
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, User $user): array
    {
        $canManage = $this->access->canManageWorkspace($user, $workspace);

        return [
            'currentWorkspace' => $workspace->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
            'workspaces' => $user->workspaces()
                ->orderBy('workspaces.created_at')
                ->orderBy('workspaces.id')
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.icon', 'workspaces.color']),
            'role' => $this->access->role($user, $workspace),
            'canManageWorkspace' => $canManage,
            'canEditWorkspace' => $this->access->canEditWorkspace($user, $workspace),
        ];
    }
}
