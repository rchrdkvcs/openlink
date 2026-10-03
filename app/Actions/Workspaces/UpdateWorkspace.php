<?php

namespace App\Actions\Workspaces;

use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;

class UpdateWorkspace
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, Workspace $workspace, string $name, ?int $preferredDomainId, ?string $icon = null, ?string $color = null): Workspace
    {
        $this->gate->forUser($actor)->authorize('update', $workspace);

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
