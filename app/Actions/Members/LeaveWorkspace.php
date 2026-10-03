<?php

namespace App\Actions\Members;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;

class LeaveWorkspace
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $user, Workspace $workspace): void
    {
        $this->gate->forUser($user)->authorize('leave', $workspace);

        $workspace->members()->where('user_id', $user->id)->delete();
    }
}
