<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace) !== null;
    }

    public function editContent(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->canEditContent() ?? false;
    }

    public function manage(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->canManageWorkspace() ?? false;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->manage($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->ownsWorkspace() ?? false;
    }

    public function transferOwnership(User $user, Workspace $workspace): bool
    {
        return $this->delete($user, $workspace);
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        $role = $user->roleIn($workspace);

        return $role !== null && ! $role->ownsWorkspace();
    }
}
