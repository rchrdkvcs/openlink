<?php

namespace App\Actions\InviteLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\InviteLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateInviteLink
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, string $role, ?int $expiresInDays, ?int $maxUses): InviteLink
    {
        $workspace = $this->access->requireManagedWorkspace($request);

        return InviteLink::create([
            'workspace_id' => $workspace->id,
            'created_by_id' => $request->user()->id,
            'role' => $role,
            'token' => Str::random(48),
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            'max_uses' => $maxUses,
        ]);
    }
}
