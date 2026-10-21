<?php

namespace App\Policies;

use App\Models\QrCodeTemplate;
use App\Models\User;

class QrCodeTemplatePolicy
{
    public function __construct(private readonly WorkspacePolicy $workspaces) {}

    public function update(User $user, QrCodeTemplate $template): bool
    {
        return $template->workspace !== null && $this->workspaces->manage($user, $template->workspace);
    }

    public function delete(User $user, QrCodeTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
