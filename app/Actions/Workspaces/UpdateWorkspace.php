<?php

namespace App\Actions\Workspaces;

use App\Models\Domain;
use App\Models\Workspace;

class UpdateWorkspace
{
    public function handle(Workspace $workspace, string $name, ?int $preferredDomainId, ?string $icon = null, ?string $color = null): Workspace
    {
        $preferredDomainId = $preferredDomainId ?: null;

        if ($preferredDomainId) {
            abort_unless(
                Domain::query()
                    ->whereKey($preferredDomainId)
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
                    ->exists(),
                422
            );
        }

        $workspace->update([
            'name' => $name,
            'preferred_domain_id' => $preferredDomainId,
            'icon' => $icon,
            'color' => $color,
        ]);

        return $workspace;
    }
}
