<?php

namespace App\Actions\Members;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferWorkspaceOwnership
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, WorkspaceMember $member): void
    {
        $workspace = $this->access->requireCurrent($request);
        abort_unless($this->access->role($request->user(), $workspace) === WorkspaceMember::ROLE_OWNER, 403);
        abort_unless($member->workspace_id === $workspace->id, 404);
        abort_if($member->user_id === $request->user()->id, 422);

        DB::transaction(function () use ($request, $workspace, $member) {
            WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $request->user()->id)
                ->update(['role' => WorkspaceMember::ROLE_ADMIN]);

            $member->update(['role' => WorkspaceMember::ROLE_OWNER]);
            $workspace->update(['owner_id' => $member->user_id]);
        });
    }
}
