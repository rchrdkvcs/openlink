<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class WorkspaceViewFactory
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function make(Workspace $workspace, User $user): WorkspaceView
    {
        $role = $this->access->role($user, $workspace);
        $canManage = $this->access->canManageWorkspace($user, $workspace);
        $canEdit = in_array($role, [
            WorkspaceMember::ROLE_OWNER,
            WorkspaceMember::ROLE_ADMIN,
            WorkspaceMember::ROLE_EDITOR,
        ], true);

        $folders = $workspace->folders()
            ->when(! $canEdit, fn ($query) => $query->whereHas('permissions', fn ($query) => $query->where('user_id', $user->id)))
            ->with('permissions.user:id,name,email')
            ->orderBy('name')
            ->get();

        return new WorkspaceView(
            workspace: $workspace,
            user: $user,
            role: $role,
            canManage: $canManage,
            canEdit: $canEdit,
            folders: $folders,
        );
    }
}
