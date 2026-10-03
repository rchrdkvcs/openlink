<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;

class DeleteWorkspace
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $owner, Workspace $workspace): Workspace
    {
        $this->gate->forUser($owner)->authorize('delete', $workspace);

        $nextWorkspace = $owner
            ->workspaces()
            ->where('workspaces.id', '!=', $workspace->id)
            ->oldest('workspaces.id')
            ->first();

        abort_unless($nextWorkspace, 422, 'You must keep at least one workspace.');

        $workspace->delete();

        return $nextWorkspace;
    }
}
