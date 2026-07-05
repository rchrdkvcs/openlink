<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceManager
{
    public function create(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name) {
            $slug = Str::slug($name);
            $base = $slug ?: 'workspace';
            $i = 1;

            while (Workspace::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }

            $workspace = Workspace::create([
                'owner_id' => $owner->id,
                'name' => $name,
                'slug' => $slug,
                'settings' => [],
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => WorkspaceMember::ROLE_OWNER,
            ]);

            return $workspace;
        });
    }

    public function update(Workspace $workspace, string $name, ?int $preferredDomainId): Workspace
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
        ]);

        return $workspace;
    }

    /**
     * Delete a workspace and return the member's next workspace.
     */
    public function destroy(User $user, Workspace $workspace): Workspace
    {
        $nextWorkspace = $user
            ->workspaces()
            ->where('workspaces.id', '!=', $workspace->id)
            ->oldest('workspaces.id')
            ->first();

        abort_unless($nextWorkspace, 422, 'You must keep at least one workspace.');

        $workspace->delete();

        return $nextWorkspace;
    }
}
