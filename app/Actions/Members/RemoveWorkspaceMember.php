<?php

namespace App\Actions\Members;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;

class RemoveWorkspaceMember
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, WorkspaceMember $member): void
    {
        $workspace = $this->access->requireManagedWorkspace($request);
        abort_unless($member->workspace_id === $workspace->id, 404);
        abort_if($member->role === WorkspaceMember::ROLE_OWNER, 403);
        abort_if($member->user_id === $request->user()->id, 403);

        self::detach($member);
    }

    public static function detach(WorkspaceMember $member): void
    {
        $member->delete();
    }
}
