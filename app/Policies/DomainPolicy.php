<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;

class DomainPolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function manage(User $user, Domain $domain): bool
    {
        return $domain->workspace !== null
            && $this->workspaces->manage($user, $domain->workspace);
    }
}
