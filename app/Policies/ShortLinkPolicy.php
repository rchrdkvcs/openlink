<?php

namespace App\Policies;

use App\Models\ShortLink;
use App\Models\User;

class ShortLinkPolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function view(User $user, ShortLink $shortLink): bool
    {
        return $this->workspaces->view($user, $shortLink->workspace);
    }

    public function update(User $user, ShortLink $shortLink): bool
    {
        return $this->workspaces->editContent($user, $shortLink->workspace);
    }

    public function delete(User $user, ShortLink $shortLink): bool
    {
        return $this->update($user, $shortLink);
    }
}
