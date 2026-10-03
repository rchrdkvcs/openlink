<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Auth\Access\Response;

class WorkspaceMemberPolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function update(User $user, WorkspaceMember $member): Response
    {
        return $this->withinReach($user, $member, fn () => $member->workspaceRole()->isAssignable()
            && $this->workspaces->manage($user, $member->workspace));
    }

    public function delete(User $user, WorkspaceMember $member): Response
    {
        return $this->withinReach($user, $member, fn () => $member->user_id !== $user->id
            && $this->update($user, $member)->allowed());
    }

    public function receiveOwnership(User $user, WorkspaceMember $member): Response
    {
        return $this->withinReach($user, $member, fn () => $this->workspaces->transferOwnership($user, $member->workspace));
    }

    private function withinReach(User $user, WorkspaceMember $member, callable $rule): Response
    {
        if (! $this->workspaces->view($user, $member->workspace)) {
            return Response::denyAsNotFound();
        }

        return $rule() ? Response::allow() : Response::deny();
    }
}
