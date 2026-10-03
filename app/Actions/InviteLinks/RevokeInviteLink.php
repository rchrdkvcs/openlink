<?php

namespace App\Actions\InviteLinks;

use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

class RevokeInviteLink
{
    public function __construct(private readonly Gate $gate) {}

    public function handle(User $actor, InviteLink $inviteLink): void
    {
        $this->gate->forUser($actor)->authorize('delete', $inviteLink);

        if ($inviteLink->revoked_at === null) {
            $inviteLink->update(['revoked_at' => now()]);
        }
    }
}
