<?php

namespace App\Actions\Workspaces;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;

class WorkspaceAccess
{
    public function current(Request $request): ?Workspace
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $query = Workspace::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id));

        $headerId = $request->headers->get('X-Workspace-Id');

        if ($headerId !== null && $headerId !== '') {
            return (clone $query)->whereKey((int) $headerId)->first();
        }

        $workspaceId = $request->hasSession() ? $request->session()->get('workspace_id') : null;

        if ($workspaceId) {
            $workspace = (clone $query)->whereKey($workspaceId)->first();

            if ($workspace) {
                return $workspace;
            }
        }

        $workspace = $query->oldest('workspaces.id')->first();

        if ($workspace && $request->hasSession()) {
            $request->session()->put('workspace_id', $workspace->id);
        }

        return $workspace;
    }

    public function requireCurrent(Request $request): Workspace
    {
        $workspace = $this->current($request);
        abort_unless($workspace, 403);

        return $workspace;
    }

    public function requireManagedWorkspace(Request $request): Workspace
    {
        $workspace = $this->requireCurrent($request);
        abort_unless($this->canManageWorkspace($request->user(), $workspace), 403);

        return $workspace;
    }

    public function requireEditableWorkspace(Request $request): Workspace
    {
        $workspace = $this->requireCurrent($request);
        abort_unless($this->canEditWorkspace($request->user(), $workspace), 403);

        return $workspace;
    }

    public function selectCurrent(Request $request, Workspace $workspace): void
    {
        abort_unless($this->isMember($request->user(), $workspace), 403);

        if ($request->hasSession()) {
            $request->session()->put('workspace_id', $workspace->id);
        }
    }

    public function requireManagedDomain(Request $request, Domain $domain): Workspace
    {
        $workspace = $this->requireManagedWorkspace($request);
        abort_unless($domain->workspace_id === $workspace->id, 403);

        return $workspace;
    }

    public function requireManagedFolder(Request $request, Folder $folder): Workspace
    {
        $workspace = $this->requireManagedWorkspace($request);
        abort_unless($folder->workspace_id === $workspace->id, 403);

        return $workspace;
    }

    public function requireViewableShortLink(Request $request, ShortLink $shortLink): Workspace
    {
        $workspace = $this->requireCurrent($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless(
            $shortLink->workspace_id === $workspace->id
            && $this->canViewShortLink($request->user(), $shortLink),
            403
        );

        return $workspace;
    }

    public function requireEditableShortLink(Request $request, ShortLink $shortLink): Workspace
    {
        $workspace = $this->requireCurrent($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless(
            $shortLink->workspace_id === $workspace->id
            && $this->canEditShortLink($request->user(), $shortLink),
            403
        );

        return $workspace;
    }

    public function requireManageableShortLink(Request $request, ShortLink $shortLink): Workspace
    {
        $workspace = $this->requireCurrent($request);
        abort_unless(
            $shortLink->workspace_id === $workspace->id
            && $this->canManageWorkspace($request->user(), $workspace),
            403
        );

        return $workspace;
    }

    public function requireEditableQrCode(Request $request, QrCode $qrCode): Workspace
    {
        if ($qrCode->short_link_id) {
            $qrCode->load('shortLink.domain');
            $qrCode->shortLink->loadMissing('workspace', 'folder.workspace');

            return $this->requireEditableShortLink($request, $qrCode->shortLink);
        }

        if ($qrCode->bio_page_id) {
            $workspace = $this->requireCurrent($request);
            $qrCode->loadMissing('bioPage.workspace');
            abort_unless(
                $qrCode->bioPage?->workspace_id === $workspace->id
                && $this->canEditWorkspace($request->user(), $workspace),
                403,
            );

            return $workspace;
        }

        $workspace = $this->requireEditableWorkspace($request);
        abort_unless($qrCode->workspace_id === $workspace->id, 403);

        return $workspace;
    }

    public function isMember(?User $user, Workspace $workspace): bool
    {
        return $user !== null
            && WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->exists();
    }

    public function role(?User $user, Workspace $workspace): ?string
    {
        if (! $user) {
            return null;
        }

        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->value('role');
    }

    public function canManageWorkspace(?User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [
            WorkspaceMember::ROLE_OWNER,
            WorkspaceMember::ROLE_ADMIN,
        ], true);
    }

    public function canEditWorkspace(?User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [
            WorkspaceMember::ROLE_OWNER,
            WorkspaceMember::ROLE_ADMIN,
            WorkspaceMember::ROLE_EDITOR,
        ], true);
    }

    public function canViewFolder(?User $user, Folder $folder): bool
    {
        $role = $this->role($user, $folder->workspace);

        if (in_array($role, [WorkspaceMember::ROLE_OWNER, WorkspaceMember::ROLE_ADMIN], true)) {
            return true;
        }

        return FolderPermission::query()
            ->where('folder_id', $folder->id)
            ->where('user_id', $user?->id)
            ->exists();
    }

    public function canEditFolder(?User $user, Folder $folder): bool
    {
        $role = $this->role($user, $folder->workspace);

        if (in_array($role, [WorkspaceMember::ROLE_OWNER, WorkspaceMember::ROLE_ADMIN], true)) {
            return true;
        }

        if ($role !== WorkspaceMember::ROLE_EDITOR) {
            return false;
        }

        return FolderPermission::query()
            ->where('folder_id', $folder->id)
            ->where('user_id', $user?->id)
            ->whereIn('permission', [FolderPermission::CAN_EDIT, FolderPermission::CAN_MANAGE])
            ->exists();
    }

    public function canViewShortLink(?User $user, ShortLink $shortLink): bool
    {
        if (! $shortLink->folder) {
            return $this->isMember($user, $shortLink->workspace);
        }

        return $this->canViewFolder($user, $shortLink->folder);
    }

    public function canEditShortLink(?User $user, ShortLink $shortLink): bool
    {
        if (! $shortLink->folder) {
            return $this->canEditWorkspace($user, $shortLink->workspace);
        }

        return $this->canEditFolder($user, $shortLink->folder);
    }
}
