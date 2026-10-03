<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function update(User $user, Folder $folder): bool
    {
        return $this->workspaces->manage($user, $folder->workspace);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $this->update($user, $folder);
    }
}
