<?php

namespace App\Actions\Pages;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceShellPayload
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Workspace $workspace, User $user): array
    {
        $canManage = $this->access->canManageWorkspace($user, $workspace);

        return [
            'currentWorkspace' => $workspace->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
            'workspaces' => $user->workspaces()
                ->orderBy('workspaces.created_at')
                ->orderBy('workspaces.id')
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.icon', 'workspaces.color']),
            'role' => $this->access->role($user, $workspace),
            'canManageWorkspace' => $canManage,
            'canEditWorkspace' => $this->access->canEditWorkspace($user, $workspace),
            'navigation' => fn () => $this->navigation($workspace),
        ];
    }

    private function navigation(Workspace $workspace): array
    {
        $links = $workspace->shortLinks();

        return [
            'folders' => $workspace->folders()
                ->withCount(['shortLinks as links_count' => fn ($query) => $query->whereNull('archived_at')])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'links_count' => (int) $folder->links_count,
                ])
                ->values(),
            'links_count' => (clone $links)->whereNull('archived_at')->count(),
            'unfiled_count' => (clone $links)->whereNull('archived_at')->whereNull('folder_id')->count(),
            'archived_count' => (clone $links)->whereNotNull('archived_at')->count(),
        ];
    }
}
