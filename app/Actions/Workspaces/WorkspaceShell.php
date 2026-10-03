<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceShell
{
    private ?WorkspaceRole $role = null;

    private bool $roleResolved = false;

    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly Request $request,
    ) {}

    public function props(): array
    {
        return [
            'currentWorkspace' => fn () => $this->workspace()?->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
            'workspaces' => fn () => $this->workspaces(),
            'role' => fn () => $this->role()?->value,
            'canManageWorkspace' => fn () => $this->role()?->canManageWorkspace() ?? false,
            'canEditWorkspace' => fn () => $this->role()?->canEditContent() ?? false,
            'navigation' => fn () => $this->navigation(),
        ];
    }

    private function user(): ?User
    {
        return $this->request->user();
    }

    private function workspace(): ?Workspace
    {
        return $this->user() ? $this->current->get() : null;
    }

    private function role(): ?WorkspaceRole
    {
        if (! $this->roleResolved) {
            $workspace = $this->workspace();
            $this->role = $workspace ? $this->user()->roleIn($workspace) : null;
            $this->roleResolved = true;
        }

        return $this->role;
    }

    private function workspaces(): Collection
    {
        return $this->user()?->workspaces()
            ->orderBy('workspaces.created_at')
            ->orderBy('workspaces.id')
            ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.icon', 'workspaces.color'])
            ?? collect();
    }

    private function navigation(): ?array
    {
        $workspace = $this->workspace();

        if (! $workspace) {
            return null;
        }

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
