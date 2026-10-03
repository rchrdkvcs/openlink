<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    public const ROLE_OWNER = WorkspaceRole::Owner->value;

    public const ROLE_ADMIN = WorkspaceRole::Admin->value;

    public const ROLE_EDITOR = WorkspaceRole::Editor->value;

    public const ROLE_VIEWER = WorkspaceRole::Viewer->value;

    protected $guarded = [];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaceRole(): WorkspaceRole
    {
        return WorkspaceRole::from($this->role);
    }
}
