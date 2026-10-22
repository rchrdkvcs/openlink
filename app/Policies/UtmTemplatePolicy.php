<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtmTemplate;

class UtmTemplatePolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function update(User $user, UtmTemplate $utmTemplate): bool
    {
        return $this->workspaces->manage($user, $utmTemplate->workspace);
    }

    public function delete(User $user, UtmTemplate $utmTemplate): bool
    {
        return $this->update($user, $utmTemplate);
    }
}
