<?php

namespace App\Actions\InviteLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\InviteLink;
use Illuminate\Http\Request;

class RevokeInviteLink
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, InviteLink $inviteLink): void
    {
        $workspace = $this->access->requireManagedWorkspace($request);
        abort_unless($inviteLink->workspace_id === $workspace->id, 403);

        if ($inviteLink->revoked_at === null) {
            $inviteLink->update(['revoked_at' => now()]);
        }
    }
}
