<?php

namespace App\Actions\Members;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\DB;

class TransferWorkspaceOwnership
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $owner, WorkspaceMember $member): void
    {
        $this->gate->forUser($owner)->authorize('receiveOwnership', $member);
        abort_if($member->user_id === $owner->id, 422);

        $workspace = $member->workspace;

        DB::transaction(function () use ($owner, $workspace, $member) {
            $workspace->members()
                ->where('user_id', $owner->id)
                ->update(['role' => WorkspaceRole::Admin->value]);

            $member->update(['role' => WorkspaceRole::Owner->value]);
            $workspace->update(['owner_id' => $member->user_id]);
        });
    }
}
