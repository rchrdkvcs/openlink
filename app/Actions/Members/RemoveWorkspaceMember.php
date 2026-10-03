<?php

namespace App\Actions\Members;

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Access\Gate;

class RemoveWorkspaceMember
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, WorkspaceMember $member): void
    {
        $this->gate->forUser($actor)->authorize('delete', $member);

        $member->delete();
    }
}
