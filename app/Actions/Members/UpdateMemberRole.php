<?php

namespace App\Actions\Members;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;

class UpdateMemberRole
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, WorkspaceMember $member, string $role): WorkspaceMember
    {
        $workspace = $this->access->requireManagedWorkspace($request);
        abort_unless($member->workspace_id === $workspace->id, 404);
        abort_if($member->role === WorkspaceMember::ROLE_OWNER, 403);

        $member->update(['role' => $role]);

        return $member;
    }
}
