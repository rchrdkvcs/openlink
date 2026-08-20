<?php

namespace App\Policies;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\BioPage;
use App\Models\User;

class BioPagePolicy
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function view(User $user, BioPage $bioPage): bool
    {
        return $this->access->isMember($user, $bioPage->workspace);
    }

    public function update(User $user, BioPage $bioPage): bool
    {
        return $this->access->canEditWorkspace($user, $bioPage->workspace);
    }

    public function publish(User $user, BioPage $bioPage): bool
    {
        return $this->access->canManageWorkspace($user, $bioPage->workspace);
    }

    public function unpublish(User $user, BioPage $bioPage): bool
    {
        return $this->publish($user, $bioPage);
    }

    public function delete(User $user, BioPage $bioPage): bool
    {
        return $this->publish($user, $bioPage);
    }
}
