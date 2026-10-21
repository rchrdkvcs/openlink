<?php

namespace App\Policies;

use App\Models\LinkPage;
use App\Models\User;

class LinkPagePolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function view(User $user, LinkPage $linkPage): bool
    {
        return $this->workspaces->view($user, $linkPage->workspace);
    }

    public function update(User $user, LinkPage $linkPage): bool
    {
        return $this->workspaces->editContent($user, $linkPage->workspace);
    }

    public function delete(User $user, LinkPage $linkPage): bool
    {
        return $this->update($user, $linkPage);
    }
}
