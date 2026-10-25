<?php

namespace App\Actions\Workspaces;

use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Arr;

class UpdateWorkspace
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, Workspace $workspace, array $data): Workspace
    {
        $this->gate->forUser($actor)->authorize('update', $workspace);

        $preferredDomainId = isset($data['preferred_domain_id']) ? (int) $data['preferred_domain_id'] : null;

        if (array_key_exists('preferred_domain_id', $data)) {
            $data['preferred_domain_id'] = $preferredDomainId ?: null;
        }

        if ($preferredDomainId) {
            abort_unless(
                Domain::query()
                    ->whereKey($preferredDomainId)
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
                    ->exists(),
                422
            );
        }

        $workspace->update(Arr::only($data, ['name', 'preferred_domain_id', 'icon', 'color']));

        return $workspace;
    }
}
