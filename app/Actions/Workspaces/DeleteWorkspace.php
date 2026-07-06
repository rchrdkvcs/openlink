<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;

class DeleteWorkspace
{
    public function handle(User $user, Workspace $workspace): Workspace
    {
        $nextWorkspace = $user
            ->workspaces()
            ->where('workspaces.id', '!=', $workspace->id)
            ->oldest('workspaces.id')
            ->first();

        abort_unless($nextWorkspace, 422, 'You must keep at least one workspace.');

        $workspace->delete();

        return $nextWorkspace;
    }
}
