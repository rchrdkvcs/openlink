<?php

namespace App\Actions\InviteLinks;

use App\Models\InviteLink;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class InviteLinkPayload
{
    public static function make(InviteLink $link): array
    {
        return [
            'id' => $link->id,
            'role' => $link->role,
            'token' => $link->token,
            'url' => $link->url(),
            'expires_at' => $link->expires_at,
            'max_uses' => $link->max_uses,
            'uses' => $link->uses,
            'is_usable' => $link->isUsable(),
            'created_at' => $link->created_at,
        ];
    }

    public static function active(Workspace $workspace): Collection
    {
        return $workspace->inviteLinks()
            ->whereNull('revoked_at')
            ->latest()
            ->get()
            ->map(fn (InviteLink $link) => self::make($link));
    }
}
