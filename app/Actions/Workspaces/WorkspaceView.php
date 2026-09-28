<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class WorkspaceView
{
    public function __construct(
        public readonly Workspace $workspace,
        public readonly User $user,
        public readonly ?string $role,
        public readonly bool $canManage,
        public readonly bool $canEdit,
        public readonly Collection $folders,
    ) {}
}
