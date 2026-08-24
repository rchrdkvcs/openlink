<?php

namespace App\Actions\Workspaces;

use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class WorkspaceView
{
    /**
     * @param  Collection<int, Folder>  $folders
     */
    public function __construct(
        public readonly Workspace $workspace,
        public readonly User $user,
        public readonly ?string $role,
        public readonly bool $canManage,
        public readonly bool $canEdit,
        public readonly Collection $folders,
    ) {}
}
