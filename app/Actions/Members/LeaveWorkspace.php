<?php

namespace App\Actions\Members;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;

class LeaveWorkspace
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request): void
    {
        $workspace = $this->access->requireCurrent($request);

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_if($member->role === WorkspaceMember::ROLE_OWNER, 403);

        RemoveWorkspaceMember::detach($member);

        if ($request->hasSession()) {
            $request->session()->forget('workspace_id');
        }
    }
}
